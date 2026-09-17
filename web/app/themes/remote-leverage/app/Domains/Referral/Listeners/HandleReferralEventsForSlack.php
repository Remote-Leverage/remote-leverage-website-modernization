<?php

declare(strict_types=1);

namespace App\Domains\Referral\Listeners;

use App\Domains\Lead\Services\SlackMessageRenderer;
use App\Domains\Referral\Events\PayoutCompleted;
use App\Domains\Referral\Events\ReferralRecorded;
use App\Domains\Referral\Events\ReferrerRegistered;
use App\Domains\Referral\Models\Payout;
use App\Domains\Referral\Models\Referral;
use App\Infrastructure\Slack\SlackTransport;
use Illuminate\Support\Facades\Log;

/**
 * The referral programme, in the channel.
 *
 * All three of these events have been dispatched since the programme shipped and none of them
 * reached Slack, so the only way to notice a new partner, a referral or a payout was to open
 * wp-admin and look — which nobody does on the day it happens. A referral in particular is
 * time-sensitive in a way a lead is not: somebody vouched for us to a person they know, and the
 * follow-up either happens while that is warm or it does not happen.
 *
 * Deliberately a separate listener from `HandleLeadEventsForSlack` rather than more methods on
 * it: they share a transport and nothing else. The lead alert carries the partial/final
 * distinction, the shadow-ban suppression and the threading, none of which mean anything here.
 *
 * ## Money
 *
 * `payout_completed` names an amount. That is the reason this one posts at all — a payout going
 * out is the single referral event with a cost attached, and a channel that sees them is a
 * channel that notices a wrong one on the day rather than at reconciliation.
 */
class HandleReferralEventsForSlack
{
    public function __construct(
        protected SlackTransport $transport = new SlackTransport,
    ) {}

    public function handleReferrerRegistered(ReferrerRegistered $event): void
    {
        $referrer = $event->referrer;

        $this->dispatch('referrer_registered', [
            'name' => (string) $referrer->name,
            'email' => (string) $referrer->email,
            'email_link' => $referrer->email ? "<mailto:{$referrer->email}|{$referrer->email}>" : '',
            'company' => (string) $referrer->company,
            'referral_code' => (string) $referrer->referral_code,
            'status' => $this->statusLabel((string) $referrer->status),
            'admin_url' => $this->adminUrl('rl-referrers-list'),
        ]);
    }

    public function handleReferralRecorded(ReferralRecorded $event): void
    {
        $referral = $event->referral;

        $this->dispatch('referral_recorded', [
            /*
             * Both of these are bound to the card's title and body, which cannot be `_when`
             * guarded, so an empty one costs the card — and before the renderer's guard existed,
             * the entire message.
             *
             * `lead_name` really can be blank: the column is NOT NULL with no default, and the
             * booking path copies it from `leads.name`, which a partial capture leaves empty.
             * The portal already assumes this and shows "Confidential Contact"; matching that
             * wording means the alert and the dashboard call the same person the same thing.
             */
            'name' => trim((string) $referral->lead_name) ?: 'Confidential Contact',
            'email' => (string) $referral->lead_email,
            'email_link' => $referral->lead_email ? "<mailto:{$referral->lead_email}|{$referral->lead_email}>" : '',
            'phone' => (string) $referral->lead_phone,
            // Same fallback as HandleLiveCallEventsForSlack::valuesFor(); a phone-only direct
            // submission deliberately stores a blank `lead_email`, so this is reachable.
            'contact_line' => implode('   ', array_filter([
                $referral->lead_email ? "<mailto:{$referral->lead_email}|{$referral->lead_email}>" : '',
                (string) $referral->lead_phone,
            ])) ?: 'No contact details captured',
            'referrer_name' => $this->referrerName($referral),
            'referral_code' => (string) ($referral->referrer?->referral_code ?? ''),
            'source' => (string) $referral->source,
            'landing_page' => (string) $referral->landing_page,
            'status' => $this->statusLabel((string) $referral->status),
            'admin_url' => $this->adminUrl('rl-referrers-referrals'),
        ]);
    }

    public function handlePayoutCompleted(PayoutCompleted $event): void
    {
        $payout = $event->payout;

        $this->dispatch('payout_completed', [
            'referrer_name' => (string) ($payout->referrer?->name ?? 'Unknown referrer'),
            'amount' => $this->money($payout),
            'status' => $this->statusLabel((string) $payout->status),
            'transfer_id' => (string) $payout->stripe_transfer_id,

            // How many referrals this payout settles, which is the number that says whether an
            // amount is plausible without opening anything.
            'referral_count' => $this->referralCount($payout),
            'admin_url' => $this->adminUrl('rl-referrers'),
        ]);
    }

    /**
     * Render a template and send it.
     *
     * @param  array<string, string>  $values
     */
    protected function dispatch(string $template, array $values): void
    {
        try {
            $rendered = app(SlackMessageRenderer::class)->render($template, $values);

            // A template that is absent or entirely conditional renders to nothing. Sending the
            // fallback text on its own would post a bare line with no card.
            if ($rendered['blocks'] === [] && trim($rendered['text']) === '') {
                return;
            }

            $this->send($rendered['text'], $rendered['blocks'], $rendered['color'] ?? null);
        } catch (\Throwable $e) {
            Log::error("HandleReferralEventsForSlack: exception sending {$template} to Slack: ".$e->getMessage());
        }
    }

    /**
     * The seam the tests override, matching `HandleLeadEventsForSlack::send()`.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array{ts: ?string, channel: ?string}|null
     */
    protected function send(string $text, array $blocks = [], ?string $color = null): ?array
    {
        return $this->transport->post($text, $blocks, $color);
    }

    /**
     * A referral can be attributed to a `Referrer` row or to a WordPress user, and older rows
     * carry neither. "Unknown referrer" beats an empty line that reads as a rendering bug.
     */
    protected function referrerName(Referral $referral): string
    {
        $name = trim((string) ($referral->referrer?->name ?? ''));

        if ($name !== '') {
            return $name;
        }

        if ($referral->referrer_user_id && function_exists('get_userdata')) {
            $user = \get_userdata((int) $referral->referrer_user_id);

            if ($user && ! empty($user->display_name)) {
                return (string) $user->display_name;
            }
        }

        return 'Unknown referrer';
    }

    /**
     * Formatted with its currency, because a bare number in a channel that also handles USD
     * leads is ambiguous exactly when it matters.
     */
    protected function money(Payout $payout): string
    {
        $currency = strtoupper(trim((string) ($payout->currency ?: 'USD')));
        $amount = number_format((float) $payout->amount, 2);

        return $currency === 'USD' ? "\${$amount}" : "{$amount} {$currency}";
    }

    protected function referralCount(Payout $payout): string
    {
        $ids = $payout->referral_ids;

        if (! is_array($ids) || $ids === []) {
            /*
             * An empty string here used to cost the entire notification. This value is bound to
             * the card's `body`, and Slack refuses a whole message over one empty text object —
             * so a payout recorded without referral ids (which is what ProcessPayoutAction
             * creates when called with none) was announced to nobody.
             *
             * Saying so is also more useful than a blank: a payout that settles no listed
             * referral is exactly the one worth a second look.
             */
            return 'No referrals listed';
        }

        $count = count($ids);

        return $count === 1 ? '1 referral' : "{$count} referrals";
    }

    /**
     * Statuses are stored as slugs and read as words. "pending_review" in a sales channel looks
     * like a leaked database value.
     */
    protected function statusLabel(string $status): string
    {
        $status = trim($status);

        return $status === '' ? '' : ucfirst(str_replace('_', ' ', $status));
    }

    protected function adminUrl(string $page): string
    {
        return function_exists('admin_url') ? (string) \admin_url("admin.php?page={$page}") : '';
    }
}
