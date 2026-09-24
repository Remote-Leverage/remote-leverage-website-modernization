<?php

declare(strict_types=1);

use App\Domains\Lead\Services\SlackMessageRenderer;

/**
 * Slack rejects an ENTIRE message with `invalid_blocks` when any text object carries an empty
 * string. Found on 2026-09-17: every referrer registration went unannounced because the card's
 * subtitle was bound to `company`, which the public form never collects.
 */
describe('empty optional text objects', function () {
    beforeEach(function () {
        // The renderer reads its templates from config; the stub container does not autoload
        // config files, so the file has to be pushed in explicitly (same as SlackReferralAlertTest).
        config(['slack-notifications' => require __DIR__.'/../../config/slack-notifications.php']);
    });

    it('drops a subtitle that resolved to nothing instead of losing the whole message', function () {
        $rendered = (new SlackMessageRenderer)->render('referrer_registered', [
            'name' => 'Dana Whitfield',
            'email' => 'dana@agency.com',
            'email_link' => '<mailto:dana@agency.com|dana@agency.com>',
            'referral_code' => 'DANA123',
            'status' => '',
            'company' => '',
        ]);

        $card = collect($rendered['blocks'] ?? [])->firstWhere('type', 'card');

        /*
         * Slack rejects the ENTIRE message with `invalid_blocks` when any text object carries an
         * empty string, so before this every referrer registration went unannounced: the row was
         * created, the event fired, the listener ran, and Slack answered ok:false.
         */
        expect($card)->not->toBeNull()
            ->and($card)->not->toHaveKey('subtitle')
            ->and($card['title']['text'])->toBe('Dana Whitfield');

        expect(json_encode($rendered))->not->toContain('"text":""');
    });

    it('keeps a subtitle that has something to say', function () {
        $rendered = (new SlackMessageRenderer)->render('referrer_registered', [
            'name' => 'Dana Whitfield',
            'email_link' => '<mailto:dana@agency.com|dana@agency.com>',
            'referral_code' => 'DANA123',
            'status' => 'active',
        ]);

        $card = collect($rendered['blocks'] ?? [])->firstWhere('type', 'card');

        expect($card['subtitle']['text'])->toBe('active');
    });
});

describe('booking alerts', function () {
    it('is on by default, so a booked call reaches the channel', function () {
        // Flipped on 2026-09-17: a real submit-and-book produced only the submission alert,
        // and the booking is the part sales acts on. Asserted against the config default with
        // no env override, which is exactly what a fresh environment gets.
        $GLOBALS['_app_config']['services']['slack']['notify_on_booking'] =
            filter_var(env('SLACK_NOTIFY_ON_BOOKING', true), FILTER_VALIDATE_BOOLEAN);

        expect(config('services.slack.notify_on_booking'))->toBeTrue();
    });
});

describe('no template can lose a message to an empty text object', function () {
    beforeEach(function () {
        config(['slack-notifications' => require __DIR__.'/../../config/slack-notifications.php']);
    });

    /**
     * The guarantee, asserted against every template at once rather than one at a time.
     *
     * Slack refuses an ENTIRE message when any text object carries an empty string, so a single
     * unbound placeholder costs the whole notification rather than the block that used it.
     * Rendering each template with every value blank is the worst case that can reach it.
     */
    it('renders every template with all values empty and emits nothing Slack would refuse', function () {
        $templates = require __DIR__.'/../../config/slack-notifications.php';
        $renderer = new SlackMessageRenderer;

        $placeholders = function (array $node) use (&$placeholders): array {
            $found = [];

            foreach ($node as $value) {
                if (is_array($value)) {
                    $found = array_merge($found, $placeholders($value));
                } elseif (is_string($value) && preg_match_all('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', $value, $m)) {
                    $found = array_merge($found, $m[1]);
                }
            }

            return $found;
        };

        $emptyTextObjects = function (array $node) use (&$emptyTextObjects): bool {
            if (isset($node['type'], $node['text'])
                && in_array($node['type'], ['mrkdwn', 'plain_text'], true)
                && is_string($node['text'])
                && trim($node['text']) === ''
            ) {
                return true;
            }

            foreach ($node as $value) {
                if (is_array($value) && $emptyTextObjects($value)) {
                    return true;
                }
            }

            return false;
        };

        $checked = 0;

        foreach ($templates as $name => $template) {
            if (! is_array($template) || ! isset($template['blocks'])) {
                continue;
            }

            $checked++;

            $rendered = $renderer->render($name, array_fill_keys($placeholders($template), ''));

            foreach ($rendered['blocks'] as $index => $block) {
                expect($emptyTextObjects($block))->toBeFalse(
                    "Template '{$name}' block {$index} carries an empty text object; Slack would reject the whole message."
                );
            }

            // A message with no blocks is still valid as long as it has fallback text, but one
            // with neither is refused — and would be silence rather than a degraded alert.
            expect(trim($rendered['text']))->not->toBe('', "Template '{$name}' would send nothing at all.");
        }

        // Guards against the loop silently matching nothing if the config shape ever changes.
        expect($checked)->toBeGreaterThan(8);
    });
});

/**
 * Slack caps a card's title and subtitle at 150 characters and its body at 200, and refuses the
 * ENTIRE message one character past either. Found on 2026-09-24: the overnight cost alert's
 * closed-day body reached 314 and every card from midnight to 08:00 went unsent.
 */
describe('card text limits', function () {
    beforeEach(function () {
        config(['slack-notifications' => require __DIR__.'/../../config/slack-notifications.php']);
    });

    it('shortens an over-long card body rather than losing the whole message', function () {
        $rendered = (new SlackMessageRenderer)->render('referrer_registered', [
            'name' => str_repeat('N', 400),
            'email_link' => str_repeat('b', 400),
            'referral_code' => 'DANA123',
            'status' => str_repeat('s', 400),
        ]);

        $card = collect($rendered['blocks'] ?? [])->firstWhere('type', 'card');

        expect($card)->not->toBeNull();

        foreach (SlackMessageRenderer::CARD_TEXT_LIMITS as $key => $limit) {
            expect(mb_strlen($card[$key]['text']))->toBe($limit)
                ->and($card[$key]['text'])->toEndWith('…');
        }
    });

    it('leaves text inside the limit alone', function () {
        $rendered = (new SlackMessageRenderer)->render('referrer_registered', [
            'name' => 'Dana Whitfield',
            'email_link' => '<mailto:dana@agency.com|dana@agency.com>',
            'referral_code' => 'DANA123',
            'status' => 'active',
        ]);

        expect(collect($rendered['blocks'])->firstWhere('type', 'card')['title']['text'])->toBe('Dana Whitfield');
    });
});
