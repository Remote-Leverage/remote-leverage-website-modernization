<?php

declare(strict_types=1);

namespace App\Domains\Referral\Support;

use App\Domains\Referral\Services\ReferralSettingsService;
use App\Domains\Referral\Services\ReferralVisitorContext;

/**
 * The offer shown to someone who has just arrived on a referral link.
 *
 * Decides *whether* to greet the visitor and *what to say*; the markup lives in
 * `partials/referral-welcome-notice`. Keeping the decision here means the same answer drives
 * both the notice and the discount recorded on the resulting Lead, so what the prospect was
 * told and what the sales team sees cannot drift apart.
 */
class ReferralWelcomeNotice
{
    public function __construct(
        protected ReferralVisitorContext $visitor,
        protected ReferralSettingsService $settings,
    ) {}

    /**
     * Show only on the request that carried the referral code.
     *
     * Not on every page view for the life of the 60-day cookie: a banner that reappears on
     * every page becomes furniture people stop reading, and this one is making a specific
     * promise that deserves to be read once.
     */
    public function shouldShow(): bool
    {
        if (! $this->visitor->arrivedFromLink()) {
            return false;
        }

        return ! empty($this->settings->get()['visitor_notice_enabled'])
            && $this->message() !== null;
    }

    /**
     * The rendered sentence, or null when there is nothing to say.
     */
    public function message(): ?string
    {
        $referrer = $this->visitor->referrer();

        if (! $referrer) {
            return null;
        }

        $name = trim((string) $referrer->name);

        if ($name === '') {
            return null;
        }

        $settings = $this->settings->get();
        $template = (string) ($settings['visitor_notice_template'] ?: ReferralSettingsService::DEFAULT_VISITOR_NOTICE);

        return strtr($template, [
            '{referrer}' => $name,
            '{amount}' => $this->formattedAmount(),
        ]);
    }

    /**
     * The message with the referrer's name and the amount emphasised.
     *
     * Returns HTML, so it is echoed unescaped — which makes the escaping here the only thing
     * standing between the settings screen and an injection. The template is escaped *first*
     * (`{referrer}` and `{amount}` survive, since braces are not escaped), then the two values
     * are substituted already escaped and already wrapped. An administrator typing `<script>`
     * into the message field therefore renders as visible text rather than executing, and a
     * referrer who signs up as `<img onerror=…>` cannot reach the page at all.
     */
    public function messageHtml(): ?string
    {
        $referrer = $this->visitor->referrer();

        if (! $referrer || $this->message() === null) {
            return null;
        }

        $settings = $this->settings->get();
        $template = (string) ($settings['visitor_notice_template'] ?: ReferralSettingsService::DEFAULT_VISITOR_NOTICE);

        return strtr(self::escape($template), [
            '{referrer}' => '<strong class="font-bold">'.self::escape(trim((string) $referrer->name)).'</strong>',
            '{amount}' => '<strong class="font-bold">'.self::escape($this->formattedAmount()).'</strong>',
        ]);
    }

    /**
     * WordPress' escaper where it exists, the PHP one everywhere else (tests, console).
     */
    protected static function escape(string $value): string
    {
        return function_exists('esc_html')
            ? (string) esc_html($value)
            : htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * The promised discount in whole currency units, for recording against a Lead.
     */
    public function amount(): int
    {
        return (int) ($this->settings->get()['visitor_discount_amount'] ?? ReferralSettingsService::DEFAULT_VISITOR_DISCOUNT);
    }

    /**
     * `$500`, not `$500.00` — a round marketing figure, written the way a person would say it.
     */
    public function formattedAmount(): string
    {
        return '$'.number_format($this->amount());
    }
}
