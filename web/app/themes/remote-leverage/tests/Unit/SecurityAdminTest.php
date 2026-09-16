<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Infrastructure\WordPress\Admin\SecurityAdmin;
use App\Infrastructure\WordPress\Admin\SecuritySkin;
use App\Infrastructure\WordPress\Security\SecuritySnapshot;
use App\Infrastructure\WordPress\Security\WordfenceConfigurator;

/*
 * WordFence rebranded as "Security".
 *
 * WordFence is not installed in the test environment, which is the point of most of what is
 * below: every entry point has to survive the plugin being absent, because the alternative is
 * a fatal on wp-admin's home page rather than a missing widget.
 *
 * The menu tests operate on $GLOBALS['menu'] / $GLOBALS['submenu'] shaped the way WordPress
 * shapes them, because that is the only contract the rebrand has.
 */

/**
 * WordPress' $menu row for WordFence's top-level item, including the notification badge it
 * appends to the title.
 *
 * @return array<int, mixed>
 */
function wordfenceMenuRow(string $badge = ''): array
{
    return [
        0 => 'Wordfence'.$badge,
        1 => 'activate_plugins',
        2 => 'Wordfence',
        3 => 'Wordfence',
        4 => 'menu-top toplevel_page_Wordfence',
        5 => 'toplevel_page_Wordfence',
        6 => 'none',
    ];
}

/**
 * The submenu WordFence registers, in registration order.
 *
 * @return list<array<int, string>>
 */
function wordfenceSubmenuRows(): array
{
    return [
        ['Dashboard', 'activate_plugins', 'Wordfence'],
        ['Firewall', 'activate_plugins', 'WordfenceWAF'],
        ['Scan', 'activate_plugins', 'WordfenceScan'],
        ['Tools', 'activate_plugins', 'WordfenceTools'],
        ['Login Security', 'wfls_show_login_security', 'WFLS'],
        ['All Options', 'activate_plugins', 'WordfenceOptions'],
        ['Help', 'activate_plugins', 'WordfenceSupport'],
        ['Wordfence Central', 'activate_plugins', 'WordfenceCentral'],
        ['<strong id="wfMenuCallout" style="color: #FCB214;">Upgrade to Care</strong>', 'activate_plugins', 'WordfenceUpgradeToCare'],
    ];
}

describe('SecurityAdmin menu rebrand', function () {
    beforeEach(function () {
        $GLOBALS['menu'] = [25 => wordfenceMenuRow()];
        $GLOBALS['submenu'] = ['Wordfence' => wordfenceSubmenuRows()];
    });

    it('renames the menu to Security and swaps in the shield icon', function () {
        (new SecurityAdmin)->rebrandMenu();

        $row = $GLOBALS['menu'][25];

        expect($row[0])->toBe('Security')
            ->and($row[3])->toBe('Security')
            ->and($row[6])->toStartWith('data:image/svg+xml;base64,');
    });

    it('leaves the menu slug alone, because re-parenting would 404 every WordFence page', function () {
        // WordPress derives a submenu page's hook name from its parent's sanitised title.
        // Change the slug and WordPress looks for security_page_WordfenceWAF while WordFence
        // registered wordfence_page_WordfenceWAF, and the Firewall screen stops resolving.
        (new SecurityAdmin)->rebrandMenu();

        expect($GLOBALS['menu'][25][2])->toBe('Wordfence')
            ->and($GLOBALS['submenu'])->toHaveKey('Wordfence');
    });

    it('carries the unread-notification badge across the rename', function () {
        $badge = ' <span class="update-plugins wf-menu-badge wf-notification-count-container" title="3">'
            .'<span class="update-count wf-notification-count-value">3</span></span>';

        $GLOBALS['menu'] = [25 => wordfenceMenuRow($badge)];

        (new SecurityAdmin)->rebrandMenu();

        expect($GLOBALS['menu'][25][0])->toBe('Security'.$badge)
            ->and($GLOBALS['menu'][25][0])->not->toContain('Wordfence');
    });

    it('removes the upsell and outbound-marketing submenu entries', function () {
        (new SecurityAdmin)->rebrandMenu();

        $slugs = array_column($GLOBALS['submenu']['Wordfence'], 2);

        expect($slugs)->not->toContain('WordfenceUpgradeToCare')
            ->not->toContain('WordfenceSupport')
            ->not->toContain('WordfenceCentral')
            ->not->toContain('WordfenceOptions');
    });

    it('keeps the working screens and renames the dashboard to Overview', function () {
        (new SecurityAdmin)->rebrandMenu();

        $labels = array_column($GLOBALS['submenu']['Wordfence'], 0, 2);

        expect($labels)->toHaveKey('WordfenceWAF')
            ->toHaveKey('WordfenceScan')
            ->toHaveKey('WordfenceTools')
            ->toHaveKey('WFLS')
            ->and($labels['Wordfence'])->toBe('Overview')
            ->and($labels['WFLS'])->toBe('Login Security');
    });

    it('reindexes the submenu so WordPress does not render gaps', function () {
        (new SecurityAdmin)->rebrandMenu();

        expect(array_keys($GLOBALS['submenu']['Wordfence']))
            ->toBe(range(0, count($GLOBALS['submenu']['Wordfence']) - 1));
    });

    it('does nothing at all when WordFence has not registered a menu', function () {
        $GLOBALS['menu'] = [2 => ['Dashboard', 'read', 'index.php', 'Dashboard', '', 'menu-dashboard', 'dashicons-dashboard']];
        unset($GLOBALS['submenu']);

        (new SecurityAdmin)->rebrandMenu();

        expect($GLOBALS['menu'][2][0])->toBe('Dashboard');
    });
});

describe('SecurityAdmin screen detection', function () {
    it('recognises every WordFence screen, including Login Security', function () {
        $admin = new SecurityAdmin;

        // WFLS hooks as wordfence_page_WFLS — a naive match on "page_Wordfence" misses it,
        // which would leave Login Security as the one unskinned screen in the section.
        expect($admin->isSecurityScreen('toplevel_page_Wordfence'))->toBeTrue()
            ->and($admin->isSecurityScreen('wordfence_page_WordfenceWAF'))->toBeTrue()
            ->and($admin->isSecurityScreen('wordfence_page_WFLS'))->toBeTrue();
    });

    it('does not claim unrelated screens', function () {
        $admin = new SecurityAdmin;

        expect($admin->isSecurityScreen('dashboard'))->toBeFalse()
            ->and($admin->isSecurityScreen('toplevel_page_rl-leads'))->toBeFalse()
            ->and($admin->isSecurityScreen('edit-post'))->toBeFalse();
    });
});

describe('SecurityAdmin section header', function () {
    beforeEach(function () {
        $GLOBALS['submenu'] = ['Wordfence' => wordfenceSubmenuRows()];
        (new SecurityAdmin)->rebrandMenu();
    });

    afterEach(function () {
        unset($GLOBALS['rl_current_screen'], $_GET['page'], $_GET['subpage']);
    });

    it("puts the Security nav on WordFence's own screens", function () {
        // Without this, Firewall and Scan open with no indication of what section you are in
        // and no way back to the overview except the sidebar.
        $GLOBALS['rl_current_screen'] = new \WP_Screen('wordfence_page_WordfenceWAF', 'admin');
        $_GET['page'] = 'WordfenceWAF';

        ob_start();
        (new SecurityAdmin)->renderSecurityHeader();
        $output = ob_get_clean();

        expect($output)->toContain('rl-security-header')
            ->toContain('rl-tabs')
            ->toContain('>Security<')
            ->toContain('rl-tab-active');
    });

    it('marks the tab for the screen you are on', function () {
        $GLOBALS['rl_current_screen'] = new \WP_Screen('wordfence_page_WordfenceScan', 'admin');
        $_GET['page'] = 'WordfenceScan';

        ob_start();
        (new SecurityAdmin)->renderSecurityHeader();
        $output = ob_get_clean();

        expect($output)->toMatch('/rl-tab rl-tab-active"[^>]*>Scan</');
    });

    it('stays out of the way on the overview, which renders its own header', function () {
        $GLOBALS['rl_current_screen'] = new \WP_Screen('toplevel_page_Wordfence', 'admin');
        $_GET['page'] = 'Wordfence';

        ob_start();
        (new SecurityAdmin)->renderSecurityHeader();

        expect(ob_get_clean())->toBe('');
    });

    it('does not touch screens that are not ours', function () {
        $GLOBALS['rl_current_screen'] = new \WP_Screen('dashboard', 'dashboard');

        ob_start();
        (new SecurityAdmin)->renderSecurityHeader();

        expect(ob_get_clean())->toBe('');
    });

    it('omits tabs for pages WordFence has not registered', function () {
        // Blocking, Live Traffic and Audit Log are conditional on WordFence settings, and
        // Live Traffic is off in this project's config. A tab to a page that does not exist
        // is a dead link.
        $GLOBALS['rl_current_screen'] = new \WP_Screen('wordfence_page_WordfenceWAF', 'admin');
        $_GET['page'] = 'WordfenceWAF';

        ob_start();
        (new SecurityAdmin)->renderSecurityHeader();
        $output = ob_get_clean();

        expect($output)->toContain('>Firewall<')
            ->and($output)->not->toContain('>Live Traffic<')
            ->and($output)->not->toContain('>Blocking<');
    });
});

describe('SecurityAdmin dashboard widget', function () {
    it("removes WordFence's activity widget and mounts ours in its place", function () {
        $GLOBALS['rl_removed_meta_boxes'] = [];
        $GLOBALS['rl_added_dashboard_widgets'] = [];

        (new SecurityAdmin)->setupDashboard();

        expect($GLOBALS['rl_removed_meta_boxes'])->toContain(SecurityAdmin::WF_WIDGET_ID)
            ->and($GLOBALS['rl_added_dashboard_widgets'])->toContain('rl_dashboard_security');
    });

    it('renders an honest empty state rather than a fatal when WordFence is absent', function () {
        app(SecuritySnapshot::class)->forget();

        ob_start();
        (new SecurityAdmin)->renderWidget();
        $output = ob_get_clean();

        expect($output)->toContain('WordFence is not active')
            ->and($output)->toContain('rl-sec-');
    });

    it('carries no emojis', function () {
        app(SecuritySnapshot::class)->forget();

        ob_start();
        (new SecurityAdmin)->renderWidget();
        $output = ob_get_clean();

        $emoji = '/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u';
        expect(preg_match($emoji, $output))->toBe(0);
    });
});

describe('SecurityAdmin value formatting', function () {
    it("renders WordFence's '1' and '' as the booleans they represent", function () {
        // The drift table compares live values against config/wordfence.php. WordFence stores
        // booleans as '1' and '', so printing them raw gives "1" and an empty cell — and an
        // empty cell reads as a rendering bug, not as "off".
        $admin = new SecurityAdmin;

        expect($admin->displayValue('1'))->toBe('on')
            ->and($admin->displayValue(''))->toBe('off')
            ->and($admin->displayValue(true))->toBe('on')
            ->and($admin->displayValue(false))->toBe('off')
            ->and($admin->displayValue(null))->toBe('off')
            ->and($admin->displayValue(20))->toBe('20');
    });

    it('falls back to an absolute date once "ago" stops being useful', function () {
        $admin = new SecurityAdmin;

        expect($admin->relativeTime(0))->toBe('never')
            ->and($admin->relativeTime(time() - 86400 * 400))->toMatch('/\d{4}$/');
    });
});

describe('SecuritySnapshot', function () {
    it('returns a complete shape when WordFence cannot be read, so views need no guards', function () {
        $data = SecuritySnapshot::unavailable();

        expect($data['available'])->toBeFalse()
            ->and($data)->toHaveKeys(['firewall', 'scan', 'blocks', 'top_ips', 'top_countries', 'failed_logins', 'updates', 'config'])
            ->and($data['blocks'])->toHaveKeys(['24h', '7d', '30d'])
            ->and($data['blocks']['7d'])->toHaveKeys(['complex', 'brute', 'blocklist', 'total'])
            ->and($data['config'])->toHaveKeys(['managed', 'drifted', 'unknown', 'enforced']);
    });

    it('reads as unavailable rather than throwing when the plugin is not loaded', function () {
        $snapshot = new SecuritySnapshot;

        expect($snapshot->available())->toBeFalse()
            ->and($snapshot->read(fresh: true)['available'])->toBeFalse();
    });
});

/**
 * A configurator reading an in-memory store, standing in for wfConfig.
 *
 * Declared here rather than shared with WordfenceConfigTest so this file stands on its own:
 * a helper that only exists if another test file happened to load first is a test that fails
 * for reasons unrelated to what it covers.
 */
function auditableConfigurator(array $stored): object
{
    return new class($stored) extends WordfenceConfigurator
    {
        public array $writes = [];

        public function __construct(public array $stored) {}

        public function available(): bool
        {
            return true;
        }

        protected function isKnownKey(string $key): bool
        {
            return true;
        }

        protected function get(string $key): mixed
        {
            return $this->stored[$key] ?? null;
        }

        protected function set(string $key, mixed $value): void
        {
            $this->writes[$key] = $value;
        }
    };
}

describe('WordfenceConfigurator audit', function () {
    it('reports drift without writing anything', function () {
        config(['wordfence.settings' => [
            'firewallEnabled' => true,
            'loginSec_maxFailures' => 20,
        ]]);

        // firewallEnabled matches ('1' is WordFence's true); maxFailures has been hand-edited.
        $subject = auditableConfigurator(['firewallEnabled' => '1', 'loginSec_maxFailures' => '5']);
        $result = $subject->audit();

        expect($result['matching'])->toBe(['firewallEnabled'])
            ->and($result['drifted'])->toHaveCount(1)
            ->and($result['drifted'][0]['key'])->toBe('loginSec_maxFailures')
            ->and($result['drifted'][0]['current'])->toBe('5')
            ->and($result['drifted'][0]['desired'])->toBe(20)
            ->and($subject->writes)->toBe([]);
    });

    it('skips rather than throws when WordFence is not loaded', function () {
        $result = (new WordfenceConfigurator)->audit();

        expect($result['skipped'])->not->toBeNull()
            ->and($result['drifted'])->toBe([]);
    });
});

describe('SecuritySkin', function () {
    it('scopes every WordFence override to the body class, so the rest of wp-admin is untouched', function () {
        $css = SecuritySkin::wordfenceSkin();

        // Each rule must be prefixed. An unscoped .wf-btn rule would repaint WordFence's
        // buttons everywhere the plugin renders, including its admin-bar dropdown.
        //
        // Comments come out first, not per-chunk: they quote WordFence selectors verbatim,
        // braces included, so splitting on '}' before stripping them tears a comment in half
        // and reports its remains as an unscoped selector.
        $stripped = (string) preg_replace('#/\*.*?\*/#s', '', $css);
        $selectors = array_filter(array_map('trim', explode('}', $stripped)));

        foreach ($selectors as $chunk) {
            if (! str_contains($chunk, '{')) {
                continue;
            }

            $selector = trim(substr($chunk, 0, strpos($chunk, '{')));

            if ($selector === '' || str_starts_with($selector, '@')) {
                continue;
            }

            foreach (explode(',', $selector) as $part) {
                $part = trim($part);

                if ($part === '') {
                    continue;
                }

                expect($part)->toContain('.rl-security-skin');
            }
        }
    });

    it('keeps the body scope at the head of every selector', function () {
        // `.rl-security-skin` lands on <body>, so it has to be the first compound in the
        // chain. Written as `#wpbody-content .rl-security-skin ...` it asks for a descendant
        // of #wpbody-content carrying the body class, which never matches — the rule is dead
        // and the screen silently keeps WordFence's styling. Shipped that way twice while
        // building this, both times reading as "the override just doesn't apply".
        $css = SecuritySkin::wordfenceSkin();
        $stripped = (string) preg_replace('#/\\*.*?\\*/#s', '', $css);

        foreach (array_filter(array_map('trim', explode('}', $stripped))) as $chunk) {
            if (! str_contains($chunk, '{')) {
                continue;
            }

            $selector = trim(substr($chunk, 0, strpos($chunk, '{')));

            if ($selector === '' || str_starts_with($selector, '@')) {
                continue;
            }

            foreach (explode(',', $selector) as $part) {
                $part = trim($part);

                if ($part === '') {
                    continue;
                }

                // The first compound — everything before the first combinator — is the one
                // matched against <body>, so that is where the scope has to be.
                $head = preg_split('/[\\s>+~]+/', $part)[0];

                expect($head)->toContain('.rl-security-skin');
            }
        }
    });

    it('remaps the zinc palette over WordFence brand colour', function () {
        $css = SecuritySkin::wordfenceSkin();

        expect($css)->toContain('#e4e4e7')   // border
            ->toContain('#18181b')            // primary
            ->toContain('#71717a')            // muted text
            ->toContain('text-transform: none !important'); // WordFence uppercases every button
    });

    it("never un-hides WordFence's responsive utilities", function () {
        // Regression: a bare `.wf-nav-pills { display: inline-flex !important }` ties
        // WordFence's `.wf-visible-xs { display: none !important }` and wins on source
        // order, which put a stray mobile "Go to" dropdown on every desktop Tools screen.
        $css = SecuritySkin::wordfenceSkin();

        $stripped = (string) preg_replace('#/\\*.*?\\*/#s', '', $css);

        foreach (array_filter(array_map('trim', explode('}', $stripped))) as $chunk) {
            if (! str_contains($chunk, '{')) {
                continue;
            }

            [$selector, $body] = explode('{', $chunk, 2);

            if (! str_contains($selector, '.wf-nav-pills') && ! str_contains($selector, '.wf-nav-tabs')) {
                continue;
            }

            if (! preg_match('/display\\s*:/', $body)) {
                continue;
            }

            // Any rule that sets display on these must exclude the hidden-utility classes.
            expect($selector)->toContain(':not(.wf-visible-xs)');
        }

        expect($css)->toContain('.rl-security-skin .wf-visible-xs { display: none !important; }');
    });

    it('ships the rl-sec primitives the widget and overview are both built from', function () {
        $css = SecuritySkin::components();

        expect($css)->toContain('.rl-sec-tile')
            ->toContain('.rl-sec-dot')
            ->toContain('.rl-sec-meter')
            ->toContain('.rl-sec-split')
            ->toContain('#rl_dashboard_security .inside');
    });
});
