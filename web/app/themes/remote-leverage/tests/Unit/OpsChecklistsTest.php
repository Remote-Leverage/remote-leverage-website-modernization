<?php

declare(strict_types=1);

/**
 * Regression guard for the three internal ops checklist tools migrated from production on
 * 2026-09-15: /hmchecklists/, /recruiterchecklists/ and /saleschecklists/.
 *
 * These are not marketing pages. Staff open them every day and submit them, and each form
 * posts straight to a live Zapier or n8n automation. The failure this file exists to catch
 * is silent: a reworded label is obvious in review, but a changed action URL, a renamed
 * input or a dropped checkbox leaves a page that still looks correct while the automation
 * downstream receives nothing, or receives a payload it cannot read.
 *
 * The expectations below were read off the live production pages with Playwright, not
 * copied from a ticket, and the patterns are rendered here rather than grepped so that a
 * mistake in the rendering loop fails too.
 */

// The patterns lean on two escapers the shared stubs do not carry. Shim them here rather
// than in tests/stubs.php, which other suites share.
if (! function_exists('wp_kses_post')) {
    function wp_kses_post($text)
    {
        return (string) $text;
    }
}

if (! function_exists('esc_textarea')) {
    function esc_textarea($text)
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Render a pattern file the way WordPress does: execute it and capture what it echoes.
 */
$render = static function (string $slug): string {
    $path = dirname(__DIR__, 2).'/patterns/'.$slug.'.php';

    expect($path)->toBeReadableFile();

    ob_start();
    include $path;

    return (string) ob_get_clean();
};

/**
 * Every <form action="..."> in the rendered markup.
 *
 * @return array<int, string>
 */
$actions = static function (string $html): array {
    preg_match_all('/<form\b[^>]*\saction="([^"]*)"/i', $html, $matches);

    return $matches[1];
};

$pages = [
    'hmchecklists' => [
        'webhook' => 'https://hooks.zapier.com/hooks/catch/10540636/2gyrtr0',
        'forms' => 20,
        'checkboxes' => 484,
        'text_fields' => ['dailyTeamUpdate', 'hiringManagerFirstName', 'hiringManagerLastName', 'hiringManagerName', 'upsell_jobId', 'upsell_jobName', 'upsell_whyNoBundle', 'upsell_whyNoPackage'],
        'checklists' => [
            '2nd Round Interviews Checklist' => 18,
            'AE Handoff Checklist' => 19,
            'Client Canceled Checklist' => 11,
            'Client No-Show Checklist' => 30,
            'Client Onboarding Meeting Checklist' => 28,
            'Client Rescheduled VA Interview Checklist' => 9,
            'Collect Client\'s Payment Method Checklist' => 17,
            'Collect Payment Checklist' => 23,
            'Daily Follow Up Checklist' => 45,
            'Invoice Paid Checklist' => 26,
            'Job Offer Checklist' => 21,
            'New Client Assigned Checklist' => 19,
            'Replacement VA Checklist' => 25,
            'Returning Client Checklist' => 15,
            'Send Hiring Agreement for the Client to Sign Checklist' => 26,
            'SplitIt Payment Checklist' => 38,
            'Start of Day Checklist' => 38,
            'VA Interviews Checklist' => 51,
            'VA Management Checklist' => 12,
            'VA No-Showed to Interview Checklist' => 13,
        ],
    ],
    'recruiterchecklists' => [
        'webhook' => 'https://n8n.srv1338052.hstgr.cloud/webhook/recruiters-checklist',
        'forms' => 7,
        'checkboxes' => 101,
        'text_fields' => ['recruiterName'],
        'checklists' => [
            'Calendar Invite for VA Interviews Checklist' => 20,
            'Client Ticket Vetting Checklist' => 17,
            'Firefighting Checklist' => 11,
            'Head of Recruiting Checklist' => 8,
            'Internal Hiring Checklist' => 26,
            'Interviews Checklist' => 12,
            'Vetting Checklist' => 7,
        ],
    ],
    'saleschecklists' => [
        'webhook' => 'https://n8n.srv1338052.hstgr.cloud/webhook/sales-checklists',
        'forms' => 10,
        'checkboxes' => 82,
        'text_fields' => ['leadName', 'salespersonName'],
        'checklists' => [
            'Capture Payment Checklist' => 6,
            'Deleting Virtual Assistant Appointments' => 9,
            'Manually Book HM Onboarding Call (When Clients Use Apple Pay/Direct Transfer/Etc)' => 2,
            'Met - Post Meeting Checklist' => 6,
            'Missed Meeting - Follow-Up Checklist' => 9,
            'New Appointment Checklist' => 9,
            'No Deal Found Checklist' => 19,
            'Prospect Canceled Meeting (Without Rescheduling)' => 8,
            'Refund Requests Checklist' => 6,
            'Sold - Deal Closed Checklist' => 8,
        ],
    ],
];

describe('internal ops checklist tools', function () use ($render, $actions, $pages) {
    test('every checklist form posts to production\'s exact webhook URL', function () use ($render, $actions, $pages) {
        foreach ($pages as $slug => $page) {
            $html = $render($slug);
            $found = $actions($html);

            // The literal URL has to survive into the markup, character for character.
            expect($html)->toContain($page['webhook']);

            expect($found)->toHaveCount($page['forms'], "{$slug} rendered the wrong number of forms");

            // And nothing may post anywhere else — a typo'd host is the silent failure.
            expect(array_values(array_unique($found)))->toBe([$page['webhook']], "{$slug} posts to an unexpected endpoint");
        }
    });

    test('every form carries production\'s checklistName and its full set of checkboxes', function () use ($render, $pages) {
        foreach ($pages as $slug => $page) {
            $html = $render($slug);

            preg_match_all('/name="checklistName" value="([^"]*)"/', $html, $named);

            $decoded = array_map(static fn (string $value): string => html_entity_decode($value, ENT_QUOTES, 'UTF-8'), $named[1]);
            sort($decoded);
            $expected = array_keys($page['checklists']);
            sort($expected);

            expect($decoded)->toBe($expected, "{$slug} changed which checklists it submits");

            preg_match_all('/<input type="checkbox"[^>]*>/', $html, $boxes);

            expect($boxes[0])->toHaveCount($page['checkboxes'], "{$slug} gained or lost checklist items");

            // The automations read the literal string "checked"; an empty value posts nothing.
            foreach ($boxes[0] as $box) {
                expect($box)->toContain('value="checked"');
            }
        }
    });

    test('the free-text field names the automations read are unchanged', function () use ($render, $pages) {
        foreach ($pages as $slug => $page) {
            $html = $render($slug);

            preg_match_all('/<(?:input type="text"|textarea)[^>]*\sname="([^"]*)"/', $html, $matches);

            $found = array_values(array_unique($matches[1]));
            sort($found);

            expect($found)->toBe($page['text_fields'], "{$slug} renamed a field the automation reads");
        }
    });

    test('the hiring-manager page keeps its three secondary live integrations', function () use ($render) {
        $html = $render('hmchecklists');

        // Beyond the checklist POST, /hmchecklists/ drives three more endpoints from JS. They
        // are as load-bearing as the form action and just as easy to drop in a refactor.
        expect($html)
            ->toContain('https://hooks.zapier.com/hooks/catch/26138149/u03014q/')
            ->toContain('https://n8n.srv1338052.hstgr.cloud/webhook/hm/jobs')
            ->toContain('https://n8n.srv1338052.hstgr.cloud/webhook/send-todo-to-slack');

        // The upsell hook receives its answers under short names, not the input names.
        expect($html)->toContain("field.name.replace(/^upsell_/, '')");
    });

    test('production\'s refresh warning is still on the page', function () use ($render) {
        // Production's own copy, in an H2. Staff rely on it, so it is not ours to tidy away.
        expect($render('hmchecklists'))->toMatch('/<h2[^>]*>\s*⚠️⚠️⚠️ REFRESH BEFORE USING ⚠️⚠️⚠️\s*<\/h2>/u');
    });

    test('each form submits into its own hidden iframe rather than navigating away', function () use ($render, $pages) {
        foreach ($pages as $slug => $page) {
            $html = $render($slug);

            preg_match_all('/<form\b[^>]*\starget="([^"]*)"/i', $html, $targets);

            expect($targets[1])->toHaveCount($page['forms'], "{$slug} has a form that would navigate to the webhook");

            foreach ($targets[1] as $target) {
                expect(str_contains($html, '<iframe name="'.$target.'"'))->toBeTrue("{$slug} is missing the iframe named {$target}");
            }
        }
    });

    test('the pages stay plain internal tools on the standard container', function () use ($render, $pages) {
        foreach ($pages as $slug => $page) {
            $html = $render($slug);

            expect(str_contains($html, 'max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8'))->toBeTrue("{$slug} left the standard container");

            // These are staff tools, not landing pages.
            expect($html)->not->toContain('font-extrabold')
                ->and($html)->not->toContain('font-black');
        }
    });

    test('each page is registered as a pattern the page can reference by slug', function () use ($pages) {
        foreach ($pages as $slug => $page) {
            $source = (string) file_get_contents(dirname(__DIR__, 2).'/patterns/'.$slug.'.php');

            expect($source)->toContain('Slug: remote-leverage/'.$slug)
                ->and($source)->toMatch('/^\s*\* Title: \S/m');
        }
    });
});
