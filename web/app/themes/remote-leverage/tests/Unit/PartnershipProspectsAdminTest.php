<?php

declare(strict_types=1);

use App\Domains\Lead\Services\EmailValidationService;
use App\Domains\PartnerHub\Actions\RecordPartnershipProspectAction;
use App\Domains\PartnerHub\Events\PartnershipProspectSubmitted;
use App\Domains\PartnerHub\Export\PartnershipProspectCsv;
use App\Domains\PartnerHub\Models\PartnershipProspect;
use App\Domains\PartnerHub\Support\PartnershipProspectFilters;
use App\Domains\PartnerHub\Support\PartnershipProspectMetrics;
use App\Infrastructure\WordPress\Admin\PartnershipAdminChrome;
use App\Infrastructure\WordPress\Admin\PartnershipOverviewAdmin;
use App\Infrastructure\WordPress\Admin\PartnershipProspectsAdmin;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

/**
 * The partnerships admin: Overview, the Prospects list, manual entry, delete and the CSV.
 *
 * The referral admin is the spec (ReferralAdminDashboard), so most of what is pinned here is the
 * same contract adapted — figures that add up, a search that finds a full name across two
 * columns, an export whose columns line up with its header — plus the two things only this side
 * has to promise: a hand-entered prospect is not announced and not run through ZeroBounce, and
 * nothing a stranger typed into the public form can become a formula in the team's spreadsheet.
 */

/** A prospect row as the form would have written it, with any column overridden. */
function adminProspect(array $attributes = []): PartnershipProspect
{
    $createdAt = $attributes['created_at'] ?? null;
    unset($attributes['created_at']);

    $prospect = new PartnershipProspect(array_merge([
        'first_name' => 'Dana',
        'last_name' => 'Whitfield',
        'email' => 'dana-'.uniqid().'@northwind-advisory.com',
        'company' => 'Northwind Advisory',
        'role' => 'Managing Partner',
        'organization_type' => 'consultancy',
        'monthly_revenue' => '250k_1m',
        'businesses_reached' => '250_1000',
        'landing_url' => 'https://remoteleverage.com/become-a-partner/?utm_source=linkedin',
        'utm_source' => 'linkedin',
    ], $attributes));

    if ($createdAt !== null) {
        $prospect->forceFill(['created_at' => $createdAt]);
    }

    $prospect->save();

    return $prospect;
}

/** @return array<string, string> What the Add form posts, passing every rule. */
function manualProspectInput(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Priya',
        'last_name' => 'Raman',
        'email' => 'priya@harbourlane.co',
        'company' => 'Harbour Lane Partners',
        'role' => 'Founder',
        'organization_type' => 'agency',
        'monthly_revenue' => '1m_5m',
        'businesses_reached' => '50_250',
        'status' => 'contacted',
        'message' => '',
        'notes' => 'Met at SaaStr, booth 14. Wants a call after the 3rd.',
    ], $overrides);
}

/** An email gate that records every address it is asked about, and passes them all. */
function recordingProspectGate(): EmailValidationService
{
    return new class extends EmailValidationService
    {
        /** @var array<int, string> */
        public array $checked = [];

        public function __construct() {}

        public function validate(string $email, ?string $ipAddress = null): array
        {
            $this->checked[] = $email;

            return ['valid' => true, 'reason' => null, 'message' => null, 'checked_by' => null];
        }
    };
}

/** @return array<int, array<int, string>> The rows of a CSV written by PartnershipProspectCsv. */
function readProspectCsv(string $csv): array
{
    $handle = fopen('php://memory', 'w+');
    fwrite($handle, $csv);
    rewind($handle);

    expect(fread($handle, 3))->toBe("\xEF\xBB\xBF");

    $rows = [];

    while (($row = fgetcsv($handle, null, ',', '"', '')) !== false) {
        $rows[] = $row;
    }

    fclose($handle);

    return $rows;
}

function exportProspects(?PartnershipProspectFilters $filters = null): string
{
    $handle = fopen('php://memory', 'w+');
    (new PartnershipProspectsAdmin)->exportCsv($handle, $filters ?? new PartnershipProspectFilters);
    rewind($handle);
    $csv = (string) stream_get_contents($handle);
    fclose($handle);

    return $csv;
}

function renderProspectsList(array $query = []): string
{
    $_GET = $query;

    ob_start();
    (new PartnershipProspectsAdmin)->render();

    return (string) ob_get_clean();
}

beforeEach(function () {
    // One sqlite database is shared by every file in the suite; start each test from empty.
    PartnershipProspect::query()->delete();
    Cache::flush();
    Event::forget(PartnershipProspectSubmitted::class);
    $GLOBALS['_wp_mock_transients'] = [];
    $GLOBALS['wp_current_user_id'] = 7;
    $_GET = [];
    $_POST = [];
});

afterEach(function () {
    Event::forget(PartnershipProspectSubmitted::class);
    app()->forgetInstance(EmailValidationService::class);
    unset($GLOBALS['wp_current_user_id']);
    $_GET = [];
    $_POST = [];
});

describe('the overview figures', function () {
    test('add up: totals, the last 7 and 30 days, the funnel, calls and conversion', function () {
        $now = CarbonImmutable::parse('2026-09-24 12:00:00');

        adminProspect(['created_at' => $now->subDays(2), 'status' => 'new']);
        adminProspect(['created_at' => $now->subDays(5), 'status' => 'contacted', 'booked_at' => $now->subDays(4)]);
        adminProspect(['created_at' => $now->subDays(12), 'status' => 'converted', 'booked_at' => $now->subDays(11)]);
        adminProspect(['created_at' => $now->subDays(40), 'status' => 'declined']);

        $m = PartnershipProspectMetrics::compute($now);

        expect($m['total'])->toBe(4)
            ->and($m['last7'])->toBe(2)
            ->and($m['last30'])->toBe(3)
            ->and($m['awaitingContact'])->toBe(1)
            ->and($m['booked'])->toBe(2)
            ->and($m['bookedRate'])->toBe(50.0)
            ->and($m['converted'])->toBe(1)
            ->and($m['conversionRate'])->toBe(25.0)
            // Every status, in the order the conversation moves, zeros included.
            ->and(array_keys($m['funnel']))->toBe(PartnershipProspect::STATUSES)
            ->and($m['funnel']['qualified'])->toBe(['count' => 0, 'share' => 0.0])
            ->and($m['funnel']['declined'])->toBe(['count' => 1, 'share' => 25.0]);
    });

    test('are all zero rather than a division by zero on an empty table', function () {
        $m = PartnershipProspectMetrics::compute();

        expect($m['total'])->toBe(0)
            ->and($m['conversionRate'])->toBe(0.0)
            ->and($m['bookedRate'])->toBe(0.0)
            ->and($m['recent'])->toBe([])
            ->and($m['topSources'])->toBe([]);
    });

    test('break each answer down in the form order, with labels, zeros and a retired slug last', function () {
        adminProspect(['monthly_revenue' => '5m_plus', 'status' => 'converted']);
        adminProspect(['monthly_revenue' => '5m_plus']);
        adminProspect(['monthly_revenue' => 'retired_band']);

        $rows = PartnershipProspectMetrics::compute()['breakdowns']['monthly_revenue'];

        expect(array_column($rows, 'slug'))->toBe(['under_50k', '50k_250k', '250k_1m', '1m_5m', '5m_plus', 'retired_band'])
            ->and($rows[4])->toMatchArray(['label' => '$5M+', 'count' => 2, 'converted' => 1])
            ->and($rows[0]['count'])->toBe(0)
            ->and($rows[5]['label'])->toBe('retired_band');
    });

    test('count sources case-insensitively, keep manual entries apart from untagged ones, and fold landing pages by path', function () {
        adminProspect(['utm_source' => 'LinkedIn', 'landing_url' => 'https://remoteleverage.com/become-a-partner/?utm_source=LinkedIn']);
        adminProspect(['utm_source' => 'linkedin', 'landing_url' => 'https://remoteleverage.com/become-a-partner/?utm_campaign=q4', 'status' => 'converted']);
        adminProspect(['utm_source' => null, 'landing_url' => 'https://remoteleverage.com/partners/']);
        adminProspect(['utm_source' => null, 'landing_url' => null, 'source' => 'manual']);

        $m = PartnershipProspectMetrics::compute();

        expect($m['topSources'])->toBe([
            ['label' => 'linkedin', 'count' => 2, 'converted' => 1],
            ['label' => 'Manual entry', 'count' => 1, 'converted' => 0],
            ['label' => 'Untagged', 'count' => 1, 'converted' => 0],
        ])
            ->and($m['topLandingPages'])->toBe([
                ['label' => '/become-a-partner/', 'count' => 2, 'converted' => 1],
                ['label' => '/partners/', 'count' => 1, 'converted' => 0],
            ])
            ->and($m['manual'])->toBe(1);
    });

    test('list the eight most recent prospects, newest first, as plain values', function () {
        foreach (range(1, 10) as $i) {
            adminProspect(['first_name' => 'Prospect'.$i]);
        }

        $recent = PartnershipProspectMetrics::compute()['recent'];

        expect($recent)->toHaveCount(8)
            ->and($recent[0]['name'])->toBe('Prospect10 Whitfield')
            ->and($recent[0]['organization'])->toBe('Consultancy or advisory firm')
            ->and($recent[0]['created_at'])->toBeInt();
    });

    test('are cached, and any write to a prospect drops the cache', function () {
        adminProspect();

        expect(PartnershipProspectMetrics::cached()['total'])->toBe(1);

        // The form writes the row, not the admin, so the model has to be what invalidates.
        $second = adminProspect();
        expect(PartnershipProspectMetrics::cached()['total'])->toBe(2);

        $second->delete();
        expect(PartnershipProspectMetrics::cached()['total'])->toBe(1);

        Cache::put(PartnershipProspectMetrics::CACHE_KEY, ['total' => 99]);
        expect(PartnershipProspectMetrics::cached()['total'])->toBe(99);
    });

    test('render on the Overview, escaped, with the shared navigation', function () {
        adminProspect(['first_name' => 'Ada', 'last_name' => 'Okafor', 'company' => 'Acme <script>alert(1)</script>']);

        ob_start();
        (new PartnershipOverviewAdmin)->render();
        $html = (string) ob_get_clean();

        expect($html)->toContain('Partnership overview')
            ->toContain('Total prospects')
            ->toContain('Awaiting contact')
            ->toContain('Calls booked')
            ->toContain('Top sources')
            ->toContain('Ada Okafor')
            ->toContain('Acme &lt;script&gt;')
            ->not->toContain('<script>alert(1)')
            ->toContain('page=rl-partnership-prospects')
            ->toContain('page=rl-partnership-settings')
            ->toContain('wp-header-end');
    });

    test('render an empty state before anyone has applied', function () {
        ob_start();
        (new PartnershipOverviewAdmin)->render();
        $html = (string) ob_get_clean();

        expect($html)->toContain('No prospects yet');
    });
});

describe('searching and filtering', function () {
    beforeEach(function () {
        adminProspect(['first_name' => 'Dana', 'last_name' => 'Whitfield', 'company' => 'Northwind Advisory', 'email' => 'dana@northwind-advisory.com', 'organization_type' => 'consultancy', 'status' => 'new']);
        adminProspect(['first_name' => 'Marcus', 'last_name' => 'Lee', 'company' => 'Brightpath Agency', 'email' => 'marcus@brightpath.io', 'organization_type' => 'agency', 'status' => 'qualified']);
        adminProspect(['first_name' => 'Sofia', 'last_name' => 'Northcott', 'company' => 'Ledger Labs', 'email' => 'sofia@ledgerlabs.dev', 'organization_type' => 'technology', 'status' => 'qualified', 'source' => 'manual']);
    });

    function prospectNames(array $query): array
    {
        return (new PartnershipProspectsAdmin)
            ->query(PartnershipProspectFilters::fromQuery($query))
            ->get()
            ->map(fn (PartnershipProspect $p) => $p->fullName())
            ->sort()
            ->values()
            ->all();
    }

    test('finds a full name across the two name columns', function () {
        expect(prospectNames(['s' => 'Dana Whitfield']))->toBe(['Dana Whitfield']);
    });

    test('matches name, email or company, case-insensitively, every word required', function () {
        expect(prospectNames(['s' => 'north']))->toBe(['Dana Whitfield', 'Sofia Northcott'])
            ->and(prospectNames(['s' => 'BRIGHTPATH.IO']))->toBe(['Marcus Lee'])
            ->and(prospectNames(['s' => 'ledger']))->toBe(['Sofia Northcott'])
            ->and(prospectNames(['s' => 'north labs']))->toBe(['Sofia Northcott'])
            ->and(prospectNames(['s' => 'nobody-matches-this']))->toBe([]);
    });

    test('narrows by status, answer and source, and ignores a value not on the list', function () {
        expect(prospectNames(['status' => 'qualified']))->toBe(['Marcus Lee', 'Sofia Northcott'])
            ->and(prospectNames(['status' => 'qualified', 'organization_type' => 'agency']))->toBe(['Marcus Lee'])
            ->and(prospectNames(['source' => 'manual']))->toBe(['Sofia Northcott'])
            // Not on any list: no filter, rather than an empty table.
            ->and(prospectNames(['status' => 'archived', 'organization_type' => "agency' OR 1=1"]))->toHaveCount(3);
    });

    test('carries only known filters into links', function () {
        $filters = PartnershipProspectFilters::fromQuery([
            's' => '  Dana   <b>Whitfield</b> ',
            'status' => 'Qualified',
            'monthly_revenue' => 'nope',
            'page' => 'something-else',
        ]);

        expect($filters->toQueryArgs())->toBe(['s' => 'Dana Whitfield', 'status' => 'qualified'])
            ->and($filters->isNarrowed())->toBeTrue()
            ->and($filters->without('s')->isNarrowed())->toBeTrue()
            ->and(PartnershipProspectFilters::fromQuery(['status' => 'new'])->isNarrowed())->toBeFalse();
    });

    test('the list shows the search, the filters and tab counts that ignore the status', function () {
        $html = renderProspectsList(['s' => 'north', 'status' => 'qualified']);

        expect($html)->toContain('Sofia Northcott')
            ->not->toContain('Marcus Lee')
            ->not->toContain('Dana Whitfield</')
            ->toContain('value="north"')
            ->toContain('name="organization_type"')
            ->toContain('name="source"')
            // "north" matches Dana (new) and Sofia (qualified): the tabs count against the
            // search, not against the status tab that is open.
            ->toMatch('/>All<span class="rl-tab-count">2</')
            ->toMatch('/>New<span class="rl-tab-count">1</')
            ->toMatch('/>Qualified<span class="rl-tab-count">1</')
            ->toContain('Showing <strong>1</strong> of <strong>1</strong>')
            ->toContain('Manual entry');
    });

    test('the export link carries the filters on screen and a nonce', function () {
        $html = renderProspectsList(['status' => 'qualified', 'organization_type' => 'agency']);

        preg_match('/href="([^"]*rl_prospect_action=export_csv[^"]*)"/', $html, $match);

        expect($match)->not->toBeEmpty()
            ->and($match[1])->toContain('status=qualified')
            ->and($match[1])->toContain('organization_type=agency')
            ->and($match[1])->toContain('_wpnonce=');
    });
});

describe('the CSV export', function () {
    test('has one cell per header on every row, in the header order, with labels not slugs', function () {
        adminProspect([
            'first_name' => 'Dana',
            'status' => 'contacted',
            'booked_at' => CarbonImmutable::parse('2026-09-20 15:30:00'),
            'calendly_event_uri' => 'https://api.calendly.com/scheduled_events/EVT-1',
            'utm_campaign' => 'q4-partners',
            'context' => ['gclid' => 'abc123'],
            'notes' => 'Follow up Monday.',
        ]);

        $rows = readProspectCsv(exportProspects());
        $record = array_combine($rows[0], $rows[1]);

        expect($rows)->toHaveCount(2)
            ->and($rows[0])->toBe(PartnershipProspectCsv::headers())
            ->and(count($rows[1]))->toBe(count(PartnershipProspectCsv::headers()))
            ->and($record['Status'])->toBe('contacted')
            ->and($record['Source'])->toBe('Form')
            ->and($record['Organization type'])->toBe('Consultancy or advisory firm')
            ->and($record['Monthly revenue'])->toBe('$250k – $1M')
            ->and($record['Businesses reached'])->toBe('250 – 1,000')
            ->and($record['Internal notes'])->toBe('Follow up Monday.')
            ->and($record['Call booked (UTC)'])->toBe('2026-09-20 15:30:00')
            ->and($record['Calendly event URI'])->toBe('https://api.calendly.com/scheduled_events/EVT-1')
            ->and($record['UTM source'])->toBe('linkedin')
            ->and($record['UTM campaign'])->toBe('q4-partners')
            ->and($record['Landing URL'])->toBe('https://remoteleverage.com/become-a-partner/?utm_source=linkedin')
            ->and($record['Context (JSON)'])->toBe('{"gclid":"abc123"}');
    });

    test('defuses anything a spreadsheet would run as a formula', function () {
        adminProspect([
            'company' => '=HYPERLINK("https://evil.example","Open")',
            'role' => '+1 555 0100',
            'message' => "-2+3\nand then some",
            'first_name' => '@SUM(A1:A9)',
        ]);

        $record = array_combine(...readProspectCsv(exportProspects()));

        expect($record['Company'])->toBe('\'=HYPERLINK("https://evil.example","Open")')
            ->and($record['Role'])->toBe("'+1 555 0100")
            ->and($record['Message'])->toBe("'-2+3\nand then some")
            ->and($record['First name'])->toBe("'@SUM(A1:A9)")
            ->and(PartnershipProspectCsv::cell("\tcmd"))->toBe("'\tcmd")
            ->and(PartnershipProspectCsv::cell('Plain = fine'))->toBe('Plain = fine')
            ->and(PartnershipProspectCsv::cell(null))->toBe('');
    });

    test('quotes commas, quotes, newlines and a trailing backslash without corrupting the next row', function () {
        adminProspect(['company' => 'Smith, Jones & "Partners"', 'message' => "Line one\nLine two \\", 'first_name' => 'First']);
        adminProspect(['first_name' => 'Second']);

        $rows = readProspectCsv(exportProspects());
        $first = array_combine($rows[0], $rows[1]);
        $second = array_combine($rows[0], $rows[2]);

        expect($rows)->toHaveCount(3)
            ->and($first['Company'])->toBe('Smith, Jones & "Partners"')
            ->and($first['Message'])->toBe("Line one\nLine two \\")
            ->and($second['First name'])->toBe('Second');
    });

    test('exports what the filters select, oldest first, and every row past a chunk', function () {
        foreach (range(1, 205) as $i) {
            adminProspect(['first_name' => 'Row'.$i, 'status' => $i === 3 ? 'converted' : 'new']);
        }

        $all = readProspectCsv(exportProspects());
        $converted = readProspectCsv(exportProspects(PartnershipProspectFilters::fromQuery(['status' => 'converted'])));

        expect($all)->toHaveCount(206)
            ->and($all[1][5])->toBe('Row1')
            ->and($all[205][5])->toBe('Row205')
            ->and($converted)->toHaveCount(2)
            ->and($converted[1][5])->toBe('Row3');
    });
});

describe('adding a prospect by hand', function () {
    test('stores it as a manual entry, with its status, notes and who entered it', function () {
        $result = (new PartnershipProspectsAdmin)->createProspect(manualProspectInput(['email' => '  Priya@HarbourLane.co ']));

        $stored = PartnershipProspect::query()->find($result['prospect']->id);

        expect($result['success'])->toBeTrue()
            ->and($result['errors'])->toBe([])
            ->and($stored->source)->toBe('manual')
            ->and($stored->isManual())->toBeTrue()
            ->and($stored->sourceLabel())->toBe('Manual entry')
            ->and($stored->status)->toBe('contacted')
            ->and($stored->email)->toBe('priya@harbourlane.co')
            ->and($stored->message)->toBeNull()
            ->and($stored->notes)->toBe('Met at SaaStr, booth 14. Wants a call after the 3rd.')
            ->and($stored->context)->toBe(['created_via' => 'wp_admin', 'created_by' => 7])
            ->and($stored->landing_url)->toBeNull();
    });

    test('is not announced in Slack and never reaches the email gate', function () {
        $announced = 0;
        Event::listen(PartnershipProspectSubmitted::class, function () use (&$announced) {
            $announced++;
        });

        $gate = recordingProspectGate();
        app()->instance(EmailValidationService::class, $gate);

        // An address the booking form's gate would refuse outright is still the one the team
        // was given; checking it would cost a ZeroBounce credit to second-guess a colleague.
        $result = (new PartnershipProspectsAdmin)->createProspect(manualProspectInput(['email' => 'someone@cuvox.de']));

        expect($result['success'])->toBeTrue()
            ->and($announced)->toBe(0)
            ->and($gate->checked)->toBe([])
            ->and(PartnershipProspect::query()->count())->toBe(1);
    });

    test('keeps the public form rules, and reports each failure under its field', function () {
        $result = (new PartnershipProspectsAdmin)->createProspect(manualProspectInput([
            'first_name' => '',
            'email' => 'not-an-address',
            'monthly_revenue' => '$100k+',
            'status' => 'archived',
            'notes' => str_repeat('a', RecordPartnershipProspectAction::NOTES_MAX + 1),
        ]));

        expect($result['success'])->toBeFalse()
            ->and(array_keys($result['errors']))->toEqualCanonicalizing(['first_name', 'email', 'monthly_revenue', 'status', 'notes'])
            ->and($result['errors']['email'][0])->toBe('Please enter a valid email address.')
            // What was typed comes back, so the form can be refilled after the redirect.
            ->and($result['input']['company'])->toBe('Harbour Lane Partners')
            ->and(PartnershipProspect::query()->count())->toBe(0);
    });

    test('defaults the status to new when none is chosen', function () {
        $result = (new PartnershipProspectsAdmin)->createProspect(manualProspectInput(['status' => '', 'notes' => '']));

        expect($result['prospect']->status)->toBe('new')
            ->and($result['prospect']->notes)->toBeNull();
    });

    test('the form stays closed until asked for, and reopens with the errors and the typed values', function () {
        adminProspect();

        expect(renderProspectsList())->toContain('Add prospect')
            ->not->toContain('name="rl_prospect_action" value="create"');

        $open = renderProspectsList(['new' => '1']);

        expect($open)->toContain('name="rl_prospect_action" value="create"')
            ->toContain('name="businesses_reached"')
            ->toContain('Nothing is posted to Slack');

        PartnershipAdminChrome::flash('prospect_form', [
            'errors' => ['email' => ['Please enter a valid email address.']],
            'input' => ['company' => 'Harbour "Lane"', 'email' => 'nope'],
        ]);

        $refilled = renderProspectsList();

        expect($refilled)->toContain('name="rl_prospect_action" value="create"')
            ->toContain('Please enter a valid email address.')
            ->toContain('value="Harbour &quot;Lane&quot;"')
            ->toContain('aria-invalid="true"')
            // A flash is read once.
            ->and(renderProspectsList())->not->toContain('name="rl_prospect_action" value="create"');
    });
});

describe('notes', function () {
    test('persist on the row and round-trip through the model', function () {
        $prospect = adminProspect(['notes' => "Call booked for Tuesday.\nAsked about white-label."]);

        expect(PartnershipProspect::query()->find($prospect->id)->notes)->toBe("Call booked for Tuesday.\nAsked about white-label.");

        $prospect->update(['notes' => null]);

        expect(PartnershipProspect::query()->find($prospect->id)->notes)->toBeNull();
    });

    test('a form submission starts with none and is recorded as the form', function () {
        $prospect = adminProspect();

        expect($prospect->notes)->toBeNull()
            ->and(PartnershipProspect::query()->find($prospect->id)->source)->toBe('form');
    });
});

describe('status and delete', function () {
    test('a status change takes only a known status', function () {
        $prospect = adminProspect();
        $admin = new PartnershipProspectsAdmin;

        expect($admin->updateStatus((int) $prospect->id, 'qualified'))->toBeTrue()
            ->and($prospect->fresh()->status)->toBe('qualified')
            ->and($admin->updateStatus((int) $prospect->id, 'archived'))->toBeFalse()
            ->and($prospect->fresh()->status)->toBe('qualified')
            ->and($admin->updateStatus(999999, 'new'))->toBeFalse();
    });

    test('delete removes the row and nothing else', function () {
        $kept = adminProspect();
        $gone = adminProspect();

        $admin = new PartnershipProspectsAdmin;

        expect($admin->deleteProspect((int) $gone->id))->toBeTrue()
            ->and(PartnershipProspect::query()->find($gone->id))->toBeNull()
            ->and(PartnershipProspect::query()->find($kept->id))->not->toBeNull()
            ->and($admin->deleteProspect((int) $gone->id))->toBeFalse();
    });

    test('every row offers a status form and a confirmed, nonced delete that posts', function () {
        adminProspect(['first_name' => "O'Brien", 'last_name' => 'Kay', 'company' => 'Kay & Co']);

        $html = renderProspectsList(['status' => 'new', 'paged' => '1']);

        expect($html)->toContain('name="rl_prospect_action" value="update_status"')
            ->toContain('name="rl_prospect_action" value="delete"')
            ->toContain('rl-btn-destructive')
            ->toContain('name="_wpnonce"')
            ->toContain('onsubmit="return window.confirm(this.dataset.confirm);"')
            // The question names who is going, escaped once, for the attribute it sits in.
            ->toContain('data-confirm="Delete O&#039;Brien Kay at Kay &amp; Co?')
            ->toContain('name="return_query" value="status=new"');
    });

    test('the list escapes what the visitor typed', function () {
        adminProspect([
            'first_name' => '<img src=x onerror=alert(1)>',
            'message' => '<script>document.cookie</script>',
        ]);

        $html = renderProspectsList();

        expect($html)->not->toContain('<img src=x')
            ->not->toContain('<script>document.cookie')
            ->toContain('&lt;script&gt;document.cookie&lt;/script&gt;');
    });
});

describe('the shared chrome', function () {
    test('links the three screens under the Partners Hub menu, marking the current one', function () {
        ob_start();
        PartnershipAdminChrome::nav(PartnershipProspectsAdmin::SLUG);
        $html = (string) ob_get_clean();

        expect($html)->toContain('edit.php?post_type=rl_partner')
            ->toContain('page=rl-partnership-overview')
            ->toContain('page=rl-partnership-prospects')
            ->toContain('page=rl-partnership-settings')
            ->toMatch('/rl-tab rl-tab-active"[^>]*aria-current="page">Prospects/')
            ->toContain('<hr class="wp-header-end">');
    });

    test('carries a flash across exactly one read, per user', function () {
        PartnershipAdminChrome::flash('probe', ['a' => 1]);

        $GLOBALS['wp_current_user_id'] = 8;
        expect(PartnershipAdminChrome::takeFlash('probe'))->toBeNull();

        $GLOBALS['wp_current_user_id'] = 7;
        expect(PartnershipAdminChrome::takeFlash('probe'))->toBe(['a' => 1])
            ->and(PartnershipAdminChrome::takeFlash('probe'))->toBeNull();
    });

    test('formats initials and shares the way the screens print them', function () {
        expect(PartnershipAdminChrome::initials('Dana Whitfield'))->toBe('DW')
            ->and(PartnershipAdminChrome::initials('Cher'))->toBe('CH')
            ->and(PartnershipAdminChrome::initials('', 'zoe@example.com'))->toBe('ZO')
            ->and(PartnershipAdminChrome::percent(25.0))->toBe('25%')
            ->and(PartnershipAdminChrome::percent(33.3))->toBe('33.3%')
            ->and(PartnershipAdminChrome::statusBadge('new'))->toContain('rl-badge-busy');
    });
});
