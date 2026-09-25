<?php

declare(strict_types=1);

namespace App\Domains\PartnerHub\Listeners;

use App\Domains\Lead\Services\SlackMessageRenderer;
use App\Domains\PartnerHub\Events\PartnershipProspectSubmitted;
use App\Domains\PartnerHub\Models\PartnershipProspect;
use App\Domains\PartnerHub\Support\PartnershipProspectOptions;
use App\Domains\PartnerHub\Support\PartnershipSettings;
use App\Infrastructure\Slack\SlackCredentials;
use App\Infrastructure\Slack\SlackTransport;
use Illuminate\Support\Facades\Log;

/**
 * A new partnership prospect, in the partnerships channel — and nowhere else.
 *
 * A separate listener from the lead and referral ones for the same reason those two are
 * separate: they share a transport and nothing else.
 *
 * ## It posts to its own channel or not at all
 *
 * Every other alert on this site posts to `SlackCredentials::channel()`, which is `#new-appts`,
 * the per-lead stream sales works from. A prospect landing there reads as a buyer and gets a
 * sales call about hiring a VA, which is the one outcome this whole domain exists to prevent. So
 * there are two conditions under which nothing is posted, and both are logged rather than
 * silently swallowed:
 *
 *  - **No channel configured** (Partners Hub → Partnership Settings). Falling back to the default
 *    channel is the failure, so there is no fallback.
 *  - **No bot token.** SlackTransport honours a channel override only on the token path; on the
 *    webhook path it logs a warning and posts to the webhook's own channel anyway — the right
 *    trade for a cost digest, and exactly the wrong one here. So this checks for a token first
 *    and does not hand the transport a message it would misdeliver.
 *
 * The prospect is saved either way. Skipping the post costs a notification, never the row.
 *
 * ## Visitor text is escaped
 *
 * Name, company, role and message are typed by an anonymous visitor and rendered as mrkdwn.
 * Unescaped, `<!channel>` in the message field pings the whole channel, and `<https://…|text>`
 * renders as a link with any label its author likes. Slack's rule is to escape `&`, `<` and `>`,
 * which neutralises both; see escape().
 */
class HandlePartnershipProspectForSlack
{
    /**
     * Slack caps a section's text at 3,000 characters and rejects the whole message past it.
     * The form allows 2,000, so this is headroom for the quote markers and the escaping, which
     * can grow a string by up to five times on a message made entirely of `<`.
     */
    protected const MESSAGE_LIMIT = 1500;

    public function __construct(
        protected SlackTransport $transport = new SlackTransport,
    ) {}

    public function handle(PartnershipProspectSubmitted $event): void
    {
        $channel = $this->channel();

        if ($channel === '') {
            Log::info('HandlePartnershipProspectForSlack: no channel set in Partnership Settings; prospect saved, nothing posted.', [
                'prospect_id' => $event->prospect->id,
            ]);

            return;
        }

        if (! $this->canTargetChannel()) {
            Log::info('HandlePartnershipProspectForSlack: no Slack bot token, and a webhook cannot post to the partnerships channel; prospect saved, nothing posted.', [
                'prospect_id' => $event->prospect->id,
                'channel' => $channel,
            ]);

            return;
        }

        try {
            $rendered = app(SlackMessageRenderer::class)->render('partnership_prospect', $this->values($event->prospect));

            if ($rendered['blocks'] === [] && trim($rendered['text']) === '') {
                return;
            }

            $this->send($rendered['text'], $rendered['blocks'], $rendered['color'] ?? null, $channel);
        } catch (\Throwable $e) {
            Log::error('HandlePartnershipProspectForSlack: exception sending to Slack: '.$e->getMessage(), [
                'prospect_id' => $event->prospect->id,
            ]);
        }
    }

    /**
     * Everything the `partnership_prospect` template binds, as finished strings.
     *
     * @return array<string, string>
     */
    public function values(PartnershipProspect $prospect): array
    {
        // Escaped like every other visitor-typed value: `"<!channel>"@example.com` is a valid
        // address to both Laravel's email rule and FILTER_VALIDATE_EMAIL, and the email gate lets
        // it through whenever ZeroBounce is unset, erroring or the domain is catch-all.
        $email = $this->escape((string) $prospect->email);
        $landing = (string) $prospect->landing_url;

        return [
            'name' => $this->escape($prospect->fullName()),
            'company' => $this->escape((string) $prospect->company),
            'role_line' => $this->escape(trim((string) $prospect->role).' at '.trim((string) $prospect->company)),
            'email' => $email,
            'email_link' => $email !== '' ? "<mailto:{$email}|{$email}>" : '',
            'organization_type' => PartnershipProspectOptions::label('organization_type', $prospect->organization_type),
            'monthly_revenue' => PartnershipProspectOptions::label('monthly_revenue', $prospect->monthly_revenue),
            'businesses_reached' => PartnershipProspectOptions::label('businesses_reached', $prospect->businesses_reached),
            'message' => $this->quote((string) $prospect->message),
            'landing_url' => $landing,
            'landing_display' => $this->display($landing),
            'admin_url' => function_exists('admin_url')
                ? (string) \admin_url('edit.php?post_type=rl_partner&page=rl-partnership-prospects')
                : '',
        ];
    }

    /**
     * The seam the tests override, matching the other Slack listeners.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array{ts: ?string, channel: ?string}|null
     */
    protected function send(string $text, array $blocks, ?string $color, string $channel): ?array
    {
        return $this->transport->post($text, $blocks, $color, null, false, $channel);
    }

    protected function channel(): string
    {
        return PartnershipSettings::slackChannel();
    }

    protected function canTargetChannel(): bool
    {
        return SlackCredentials::botToken() !== '';
    }

    /**
     * Slack's own escaping rule for mrkdwn: these three characters and nothing else.
     */
    protected function escape(string $text): string
    {
        return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], trim($text));
    }

    /**
     * The optional message as a block quote. Slack quotes one line per `>`, so every line gets
     * its own marker, and a blank message stays blank so the template's `_when` drops the block.
     */
    protected function quote(string $message): string
    {
        $message = trim($message);

        if ($message === '') {
            return '';
        }

        if (mb_strlen($message) > self::MESSAGE_LIMIT) {
            $message = rtrim(mb_substr($message, 0, self::MESSAGE_LIMIT - 1)).'…';
        }

        $lines = preg_split('/\R/u', $this->escape($message)) ?: [];

        return implode("\n", array_map(static fn (string $line) => '>'.$line, $lines));
    }

    /**
     * Host and path, without the query string that UTM-tagged links drag along.
     */
    protected function display(string $url): string
    {
        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);
        $display = ($parts['host'] ?? '').($parts['path'] ?? '');

        return $display !== '' ? $display : $url;
    }
}
