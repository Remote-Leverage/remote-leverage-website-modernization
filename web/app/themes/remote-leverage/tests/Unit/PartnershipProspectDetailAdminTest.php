<?php

declare(strict_types=1);

use App\Domains\PartnerHub\Actions\RecordPartnershipProspectAction;
use App\Domains\PartnerHub\Actions\UpdatePartnershipProspectAction;
use App\Domains\PartnerHub\Models\PartnershipProspect;
use App\Domains\PartnerHub\Support\PartnershipProspectMetrics;
use App\Infrastructure\WordPress\Admin\PartnershipAdminChrome;
use App\Infrastructure\WordPress\Admin\PartnershipProspectDetailAdmin;
use App\Infrastructure\WordPress\Admin\PartnershipProspectsAdmin;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * Partners Hub → Prospects → one prospect.
 *
 * What is pinned here is what the partnerships team relies on when they open a prospect: every
 * answer and every scrap of attribution that exists, nothing printed that was not captured, the
 * way back to the same place in the list, and a save that keeps what they typed when it is
 * refused. The handler's nonce and redirect are WordPress's; everything behind them is plain
 * methods, tested directly.
 */
function detailProspect(array $attributes = []): PartnershipProspect
{
    return PartnershipProspect::query()->create(array_merge([
        'first_name' => 'Dana',
        'last_name' => 'Whitfield',
        'email' => 'dana@northwind-advisory.com',
        'company' => 'Northwind Advisory',
        'role' => 'Managing Partner',
        'organization_type' => 'consultancy',
        'monthly_revenue' => '250k_1m',
        'businesses_reached' => '250_1000',
        'message' => 'We advise 300 dental practices that keep asking about remote staff.',
    ], $attributes));
}

/** The screen as an admin sees it, for a query string. */
function renderProspectDetail(array $query): string
{
    $_GET = $query;

    ob_start();

    try {
        (new PartnershipProspectDetailAdmin)->render();
    } finally {
        $html = (string) ob_get_clean();
    }

    return $html;
}

function resetProspectDetailGlobals(): void
{
    $_GET = [];
    $_POST = [];
    $GLOBALS['_wp_mock_transients'] = [];
    unset($GLOBALS['_wp_mock_capabilities'], $GLOBALS['plugin_page'], $GLOBALS['rl_inline_styles']);
}

beforeEach(function () {
    PartnershipProspect::query()->delete();
    Cache::flush();
    resetProspectDetailGlobals();
});

afterEach(function () {
    resetProspectDetailGlobals();
});

describe('the address', function () {
    test('opens one prospect under the Partners Hub menu', function () {
        expect(PartnershipProspectDetailAdmin::url(7))
            ->toBe('https://remoteleverage.com/wp-admin/edit.php?post_type=rl_partner&page=rl-partnership-prospect&prospect=7');
    });

    test('carries the list position, and cannot be pointed at another screen or prospect', function () {
        $url = PartnershipProspectDetailAdmin::url(7, ['s' => 'dana', 'status' => 'new', 'paged' => 2, 'page' => 'elsewhere', 'prospect' => 9]);

        expect($url)->toContain('page=rl-partnership-prospect&prospect=7&s=dana&status=new&paged=2')
            ->and($url)->not->toContain('elsewhere')
            ->and($url)->not->toContain('prospect=9');
    });

    test('keeps only the list arguments the list itself accepts', function () {
        expect(PartnershipProspectDetailAdmin::listArgs([
            'page' => PartnershipProspectDetailAdmin::SLUG,
            'prospect' => '5',
            's' => '  dana  ',
            'status' => 'bogus',
            'source' => 'manual',
            'paged' => '3',
            'utm_source' => 'linkedin',
        ]))->toBe(['s' => 'dana', 'source' => 'manual', 'paged' => 3]);

        // Page one is the list's default, so it is not worth carrying.
        expect(PartnershipProspectDetailAdmin::listArgs(['paged' => '1']))->toBe([]);
    });
});

describe('the screen', function () {
    test('heads with who they are, how to reach them, and when and how they arrived', function () {
        $prospect = detailProspect(['status' => 'contacted']);

        $html = renderProspectDetail(['page' => PartnershipProspectDetailAdmin::SLUG, 'prospect' => (string) $prospect->id]);

        expect($html)->toContain('<h1 class="rl-admin-title">Dana Whitfield</h1>')
            ->and($html)->toContain('Managing Partner at Northwind Advisory')
            ->and($html)->toContain('href="mailto:dana@northwind-advisory.com"')
            ->and($html)->toContain(PartnershipAdminChrome::statusBadge('contacted'))
            ->and($html)->toContain('Submitted '.PartnershipAdminChrome::when($prospect->created_at))
            ->and($html)->toContain('Source: Form');
    });

    test('says a hand-entered prospect was added, not submitted', function () {
        $prospect = detailProspect(['source' => 'manual']);

        $html = renderProspectDetail(['prospect' => (string) $prospect->id]);

        expect($html)->toContain('Added '.PartnershipAdminChrome::when($prospect->created_at))
            ->and($html)->toContain('Source: Manual entry');
    });

    test('shows the answers as words, and the whole message', function () {
        $prospect = detailProspect();

        $html = renderProspectDetail(['prospect' => (string) $prospect->id]);

        expect($html)->toContain('Consultancy or advisory firm')
            ->and($html)->toContain('$250k – $1M')
            ->and($html)->toContain('250 – 1,000')
            ->and($html)->toContain('We advise 300 dental practices that keep asking about remote staff.');

        $quiet = detailProspect(['email' => 'quiet@northwind-advisory.com', 'message' => null]);

        expect(renderProspectDetail(['prospect' => (string) $quiet->id]))->toContain('No message.');
    });

    test('prints the attribution that was captured and leaves out what was not', function () {
        $prospect = detailProspect([
            'landing_url' => 'https://remoteleverage.com/become-a-partner/?utm_source=linkedin',
            'utm_source' => 'linkedin',
            'utm_campaign' => 'partners-q4',
            'context' => ['gclid' => 'abc123', 'unmapped' => ['ref_code' => 'NW-1'], 'source_form' => 'PartnershipProspectForm'],
        ]);

        $html = renderProspectDetail(['prospect' => (string) $prospect->id]);

        expect($html)->toContain('Landing page')
            ->and($html)->toContain('href="https://remoteleverage.com/become-a-partner/?utm_source=linkedin"')
            ->and($html)->toContain('UTM source</th><td>linkedin')
            ->and($html)->toContain('UTM campaign</th><td>partners-q4')
            ->and($html)->not->toContain('UTM medium')
            ->and($html)->not->toContain('UTM term')
            ->and($html)->not->toContain('Referrer</th>');

        // `context` one key per row, with a nested list as indented JSON rather than a blob.
        expect($html)->toContain('<span class="rl-mono">gclid</span></th><td>abc123')
            ->and($html)->toContain('<pre class="rl-json">{'."\n".'    &quot;ref_code&quot;: &quot;NW-1&quot;'."\n".'}</pre>')
            ->and($html)->toContain('PartnershipProspectForm');
    });

    test('never links a referrer that is not a web address', function () {
        $prospect = detailProspect(['referrer_url' => 'javascript:alert(document.cookie)']);

        $html = renderProspectDetail(['prospect' => (string) $prospect->id]);

        expect($html)->toContain('Referrer</th><td>javascript:alert(document.cookie)')
            ->and($html)->not->toContain('href="javascript:');
    });

    test('says why there is no attribution rather than printing an empty table', function () {
        $submitted = detailProspect();
        $manual = detailProspect(['email' => 'met@event.test', 'source' => 'manual']);

        expect(renderProspectDetail(['prospect' => (string) $submitted->id]))
            ->toContain('Nothing was captured about where they came from.')
            ->not->toContain('<p class="rl-subhead">Context</p>');

        expect(renderProspectDetail(['prospect' => (string) $manual->id]))
            ->toContain('Entered by hand, so there is no landing page or campaign to show.');
    });

    test('shows the booked call, or that there is none', function () {
        $unbooked = detailProspect();

        expect(renderProspectDetail(['prospect' => (string) $unbooked->id]))->toContain('No call booked.');

        $booked = detailProspect([
            'email' => 'booked@northwind-advisory.com',
            'booked_at' => '2026-09-24 15:30:00',
            'calendly_event_uri' => 'https://api.calendly.com/scheduled_events/EVT123',
            'calendly_invitee_uri' => 'https://api.calendly.com/scheduled_events/EVT123/invitees/INV456',
        ]);

        $html = renderProspectDetail(['prospect' => (string) $booked->id]);

        expect($html)->not->toContain('No call booked.')
            ->and($html)->toContain('Booked</th><td>'.PartnershipAdminChrome::when($booked->booked_at))
            ->and($html)->toContain('<span class="rl-mono">https://api.calendly.com/scheduled_events/EVT123</span>')
            ->and($html)->toContain('invitees/INV456');
    });

    test('holds the status and the notes in one form that posts back here', function () {
        $prospect = detailProspect(['status' => 'qualified', 'notes' => 'Intro call with Sam on Tuesday.']);

        $html = renderProspectDetail(['prospect' => (string) $prospect->id]);

        expect($html)->toContain('<form method="post" action="'.PartnershipProspectDetailAdmin::url((int) $prospect->id).'">')
            ->and($html)->toContain('name="_wpnonce"')
            ->and($html)->toContain('name="rl_prospect_detail_action" value="save"')
            ->and($html)->toContain('name="prospect_id" value="'.$prospect->id.'"')
            ->and($html)->toContain('<option value="qualified" selected>')
            ->and($html)->toContain('name="notes" rows="8">Intro call with Sam on Tuesday.</textarea>');
    });

    test('leads back to the list where it was left, and deletes through the list', function () {
        $prospect = detailProspect();

        $html = renderProspectDetail(['prospect' => (string) $prospect->id, 's' => 'dana', 'status' => 'new', 'paged' => '2']);

        $list = PartnershipAdminChrome::url(PartnershipProspectsAdmin::SLUG, ['s' => 'dana', 'status' => 'new', 'paged' => 2]);

        expect($html)->toContain('href="'.$list.'"')
            ->and($html)->toContain('action="'.PartnershipProspectDetailAdmin::url((int) $prospect->id, ['s' => 'dana', 'status' => 'new', 'paged' => 2]).'"')
            ->and($html)->toContain('name="rl_prospect_action" value="delete"')
            ->and($html)->toContain('name="return_query" value="s=dana&amp;status=new&amp;paged=2"');
    });

    test('escapes everything the prospect or the team typed', function () {
        $prospect = detailProspect([
            'first_name' => '<script>alert(1)</script>',
            'company' => '<img src=x onerror=alert(2)>',
            'message' => '<b>bold</b>',
            'notes' => '</textarea><script>alert(3)</script>',
            'utm_source' => '"><script>alert(4)</script>',
            'context' => ['<k>' => '<v>'],
        ]);

        $html = renderProspectDetail(['prospect' => (string) $prospect->id]);

        expect($html)->not->toContain('<script>')
            ->and($html)->not->toContain('<img src=x')
            ->and($html)->not->toContain('<b>bold</b>')
            ->and($html)->not->toContain('<k>')
            ->and($html)->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')
            ->and($html)->toContain('&lt;/textarea&gt;&lt;script&gt;alert(3)&lt;/script&gt;</textarea>');
    });

    test('says a missing prospect is not found, with the way back, whatever the id looks like', function () {
        foreach ([['prospect' => '999999'], ['prospect' => 'abc'], ['prospect' => ['1']], []] as $query) {
            $html = renderProspectDetail($query + ['status' => 'declined']);

            expect($html)->toContain('Prospect not found')
                ->and($html)->toContain('href="'.PartnershipAdminChrome::url(PartnershipProspectsAdmin::SLUG, ['status' => 'declined']).'"')
                ->and($html)->not->toContain('<form');
        }
    });

    test('is for administrators only', function () {
        $prospect = detailProspect();
        $GLOBALS['_wp_mock_capabilities'] = [];

        expect(fn () => renderProspectDetail(['prospect' => (string) $prospect->id]))
            ->toThrow(RuntimeException::class, 'You do not have permission');
    });
});

describe('saving the status and notes', function () {
    test('writes both and reports which changed', function () {
        $prospect = detailProspect();

        $changed = app(UpdatePartnershipProspectAction::class)->execute($prospect, [
            'status' => 'contacted',
            'notes' => "  Emailed Dana.\r\nFollow up Friday.  ",
        ]);

        $fresh = $prospect->fresh();

        expect($changed)->toBe(['status', 'notes'])
            ->and($fresh->status)->toBe('contacted')
            ->and($fresh->notes)->toBe("Emailed Dana.\nFollow up Friday.");
    });

    test('touches only the fields it is given', function () {
        $prospect = detailProspect(['notes' => 'Keep me.']);

        $changed = app(UpdatePartnershipProspectAction::class)->execute($prospect, ['status' => 'qualified']);

        expect($changed)->toBe(['status'])
            ->and($prospect->fresh()->notes)->toBe('Keep me.');
    });

    test('stores cleared notes as no notes', function () {
        $prospect = detailProspect(['notes' => 'Old note.']);

        $changed = app(UpdatePartnershipProspectAction::class)->execute($prospect, ['status' => 'new', 'notes' => "  \r\n "]);

        expect($changed)->toBe(['notes'])
            ->and($prospect->fresh()->notes)->toBeNull();
    });

    test('does not write when nothing changed, so the Overview keeps its cache', function () {
        $prospect = detailProspect(['status' => 'qualified', 'notes' => "Line one\nLine two"]);
        Cache::put(PartnershipProspectMetrics::CACHE_KEY, 'cached', 180);

        $changed = app(UpdatePartnershipProspectAction::class)->execute($prospect, [
            'status' => 'qualified',
            'notes' => "Line one\r\nLine two",
        ]);

        expect($changed)->toBe([])
            ->and(Cache::get(PartnershipProspectMetrics::CACHE_KEY))->toBe('cached');
    });

    test('refuses a status that is not on the list, or notes over the limit, and changes nothing', function () {
        $prospect = detailProspect(['notes' => 'Original.']);
        $action = app(UpdatePartnershipProspectAction::class);

        foreach ([
            'status' => ['status' => 'promoted', 'notes' => 'Changed.'],
            'notes' => ['status' => 'contacted', 'notes' => str_repeat('a', RecordPartnershipProspectAction::NOTES_MAX + 1)],
        ] as $field => $input) {
            try {
                $action->execute($prospect, $input);
                $this->fail('Expected the save to be refused.');
            } catch (ValidationException $e) {
                expect(array_keys($e->errors()))->toBe([$field]);
            }
        }

        $fresh = $prospect->fresh();

        expect($fresh->status)->toBe('new')
            ->and($fresh->notes)->toBe('Original.');
    });

    test('returns to the same prospect and list position, and confirms once', function () {
        $prospect = detailProspect();
        $listArgs = ['s' => 'dana', 'paged' => 2];

        $url = (new PartnershipProspectDetailAdmin)->save((int) $prospect->id, ['status' => 'qualified', 'notes' => 'Strong fit.'], $listArgs);

        expect($url)->toBe(PartnershipProspectDetailAdmin::url((int) $prospect->id, $listArgs))
            ->and($prospect->fresh()->status)->toBe('qualified');

        $query = ['prospect' => (string) $prospect->id];

        expect(renderProspectDetail($query))->toContain('Status changed to Qualified. Notes saved.');
        expect(renderProspectDetail($query))->not->toContain('notice-success');
    });

    test('says so when a save changed nothing', function () {
        $prospect = detailProspect();

        (new PartnershipProspectDetailAdmin)->save((int) $prospect->id, ['status' => 'new', 'notes' => '']);

        expect(renderProspectDetail(['prospect' => (string) $prospect->id]))
            ->toContain('Nothing to save: the status and notes were already as shown.');
    });

    test('keeps what was typed when the save is refused', function () {
        $prospect = detailProspect(['notes' => 'Stored note.']);

        (new PartnershipProspectDetailAdmin)->save((int) $prospect->id, [
            'status' => 'contacted',
            'notes' => str_repeat('b', RecordPartnershipProspectAction::NOTES_MAX + 1),
        ]);

        $html = renderProspectDetail(['prospect' => (string) $prospect->id]);

        expect($prospect->fresh()->notes)->toBe('Stored note.')
            ->and($html)->toContain('notice-error')
            ->and($html)->toContain('Nothing was saved.')
            ->and($html)->toContain('<span class="rl-field-error">')
            ->and($html)->toContain('<option value="contacted" selected>')
            ->and($html)->toContain(str_repeat('b', 200))
            ->and($html)->not->toContain('Stored note.');
    });

    test('shows the stored status after refusing one that is not on the list, not the first option', function () {
        $prospect = detailProspect(['status' => 'qualified']);

        (new PartnershipProspectDetailAdmin)->save((int) $prospect->id, ['status' => 'promoted', 'notes' => 'Typed.']);

        $html = renderProspectDetail(['prospect' => (string) $prospect->id]);

        expect($html)->toContain('<option value="qualified" selected>')
            ->and($html)->toContain('rows="8">Typed.</textarea>');
    });

    test('shows one prospect\'s confirmation on that prospect only', function () {
        $saved = detailProspect();
        $other = detailProspect(['email' => 'other@northwind-advisory.com']);

        (new PartnershipProspectDetailAdmin)->save((int) $saved->id, ['status' => 'qualified', 'notes' => '']);

        expect(renderProspectDetail(['prospect' => (string) $other->id]))->not->toContain('Status changed');
    });

    test('lands on not found when the prospect was deleted before the save', function () {
        $prospect = detailProspect();
        $id = (int) $prospect->id;
        $prospect->delete();

        $url = (new PartnershipProspectDetailAdmin)->save($id, ['status' => 'qualified', 'notes' => 'Too late.']);

        expect($url)->toBe(PartnershipProspectDetailAdmin::url($id))
            ->and(renderProspectDetail(['prospect' => (string) $id]))->toContain('Prospect not found');
    });

    test('the handler ignores other forms, and anyone without manage_options', function () {
        $admin = new PartnershipProspectDetailAdmin;

        // Both return before check_admin_referer(), which the test harness does not define — so
        // reaching it would throw.
        $_POST = ['rl_prospect_action' => 'update_status', 'prospect_id' => '1', 'status' => 'qualified'];
        expect(fn () => $admin->handleActions())->not->toThrow(Throwable::class);

        $_POST = ['rl_prospect_detail_action' => 'save', 'prospect_id' => '1', 'status' => 'qualified'];
        $GLOBALS['_wp_mock_capabilities'] = [];
        expect(fn () => $admin->handleActions())->not->toThrow(Throwable::class);
    });
});

describe('in the admin menu', function () {
    test('marks Prospects as current while a prospect is open, and nothing else', function () {
        $admin = new PartnershipProspectDetailAdmin;

        $GLOBALS['plugin_page'] = PartnershipProspectDetailAdmin::SLUG;
        expect($admin->highlightProspects(null))->toBe(PartnershipProspectsAdmin::SLUG);

        $GLOBALS['plugin_page'] = PartnershipProspectsAdmin::SLUG;
        expect($admin->highlightProspects('edit.php?post_type=rl_partner'))->toBe('edit.php?post_type=rl_partner');
    });

    test('loads its styles on its own screen and not on the list, whose slug contains its own', function () {
        $admin = new PartnershipProspectDetailAdmin;

        $admin->enqueueStyles('rl_partner_page_'.PartnershipProspectsAdmin::SLUG);
        expect($GLOBALS['rl_inline_styles'] ?? [])->toBe([]);

        $admin->enqueueStyles('rl_partner_page_'.PartnershipProspectDetailAdmin::SLUG);
        expect(implode("\n", $GLOBALS['rl_inline_styles']['wp-admin']))
            ->toContain(PartnershipAdminChrome::css())
            ->toContain(PartnershipProspectDetailAdmin::css());
    });
});
