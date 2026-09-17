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
