<?php

declare(strict_types=1);

namespace App\Infrastructure\Slack;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The one way this application talks to Slack.
 *
 * Lifted out of `HandleLeadEventsForSlack` when the referral and live-call alerts arrived:
 * three listeners each carrying their own copy of the token/webhook precedence is three places
 * to fix when an environment is wired differently, and two of them would have been fixed late.
 *
 * ## Why the return type is an array and not a bool
 *
 * Threading needs the parent message's `ts`. A bool throws it away, which is why the lead alert
 * could not reply to itself before — every lifecycle event landed as a fresh top-level message
 * and the channel showed four disconnected cards for one person. `post()` hands back what Slack
 * returned so the caller can store it and reply to it later.
 *
 * ## The webhook fallback cannot thread
 *
 * An incoming webhook answers `ok` and nothing else — no `ts`, no channel. So the webhook path
 * succeeds with a null `ts`, and a caller that wanted to thread simply posts at top level next
 * time. That is a degradation, not a failure: an environment with no bot token still gets its
 * alerts, it just gets them flat.
 */
class SlackTransport
{
    /**
     * Post a message, optionally as a reply in an existing thread.
     *
     * @param  string  $text  Notification preview and the fallback for clients without blocks.
     * @param  array<int, array<string, mixed>>  $blocks
     * @param  string|null  $color  Non-null boxes the message in a coloured attachment.
     * @param  string|null  $threadTs  Reply under this parent rather than posting at top level.
     * @param  bool  $broadcast  Also surface a threaded reply in the channel. For the replies
     *                           that people must not miss — a booking, a block — where burying
     *                           it in a collapsed thread would be worse than not threading.
     * @return array{ts: ?string, channel: ?string}|null Null when nothing was sent.
     */
    public function post(
        string $text,
        array $blocks = [],
        ?string $color = null,
        ?string $threadTs = null,
        bool $broadcast = false,
    ): ?array {
        // Environment first, admin setting second — see SlackCredentials for why the setting
        // exists at all.
        $token = SlackCredentials::botToken();
        $channel = SlackCredentials::channel();

        if ($token !== '' && $channel !== '') {
            return $this->postWithToken($token, $channel, $text, $blocks, $color, $threadTs, $broadcast);
        }

        return $this->postWithWebhook($text, $blocks, $threadTs);
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array{ts: ?string, channel: ?string}|null
     */
    protected function postWithToken(
        string $token,
        string $channel,
        string $text,
        array $blocks,
        ?string $color,
        ?string $threadTs,
        bool $broadcast,
    ): ?array {
        $payload = [
            'channel' => $channel,
            'text' => $text,
            'mrkdwn' => true,

            /*
             * No link previews. The landing page is a marketing page, so Slack unfurls it
             * into a card with the hero copy and a reading time — several times taller than
             * the alert itself, and it buries the lead's details under an advert for our
             * own site.
             */
            'unfurl_links' => false,
            'unfurl_media' => false,
        ];

        if ($threadTs !== null && $threadTs !== '') {
            $payload['thread_ts'] = $threadTs;

            // Only meaningful on a reply; Slack rejects it on a top-level message.
            if ($broadcast) {
                $payload['reply_broadcast'] = true;
            }
        }

        if ($blocks !== []) {
            /*
             * A colour means box it: blocks nested in an attachment render with a coloured
             * bar down the left and read as one unit rather than as loose blocks in the
             * channel. Without a colour they go at top level, unboxed.
             *
             * `text` stays on the message itself either way — it is the notification
             * preview, and Slack does not take it from an attachment.
             */
            if ($color !== null) {
                $payload['attachments'] = [['color' => $color, 'blocks' => $blocks]];
            } else {
                $payload['blocks'] = $blocks;
            }
        }

        $response = Http::withToken($token)
            ->timeout(5)
            ->post('https://slack.com/api/chat.postMessage', $payload);

        // Slack answers 200 with `ok: false` on an application error (bad token, missing
        // scope, bot not in channel), so the status code alone is not the outcome.
        if ($response->successful() && $response->json('ok') === true) {
            return [
                'ts' => $response->json('ts') !== null ? (string) $response->json('ts') : null,
                'channel' => $response->json('channel') !== null ? (string) $response->json('channel') : null,
            ];
        }

        $error = $response->json('error');

        /*
         * A reply whose parent Slack no longer has is the one failure worth retrying, because
         * it is self-inflicted: the stored `ts` outlived the message (deleted, or the channel
         * changed under us). Posting it flat is strictly better than dropping it.
         */
        if ($threadTs !== null && in_array($error, ['thread_not_found', 'message_not_found'], true)) {
            Log::info('SlackTransport: parent message is gone, posting at top level instead.', [
                'thread_ts' => $threadTs,
            ]);

            return $this->postWithToken($token, $channel, $text, $blocks, $color, null, false);
        }

        Log::warning('SlackTransport: chat.postMessage rejected', [
            'status' => $response->status(),
            'error' => $error,
        ]);

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array{ts: ?string, channel: ?string}|null
     */
    protected function postWithWebhook(string $text, array $blocks, ?string $threadTs): ?array
    {
        $webhookUrl = $this->webhookUrl();

        if ($webhookUrl === '') {
            Log::info('SlackTransport: no bot token or webhook URL configured; nothing sent.');

            return null;
        }

        $response = Http::timeout(5)->post($webhookUrl, array_filter([
            'text' => $text,
            'blocks' => $blocks ?: null,
            'thread_ts' => $threadTs ?: null,
            'unfurl_links' => false,
        ]));

        // No `ts` comes back from an incoming webhook, so nothing downstream can thread onto
        // this message. See the class docblock.
        return $response->successful() ? ['ts' => null, 'channel' => null] : null;
    }

    protected function webhookUrl(): string
    {
        $url = (string) (config('services.slack.webhook_url') ?? '');

        if ($url === '' && function_exists('get_option')) {
            $url = (string) (get_option('rl_jlc_slack_webhook_url') ?: get_option('rl_slack_webhook_url') ?: '');
        }

        return $url;
    }
}
