<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Domains\Lead\Actions\CaptureLeadAction;
use App\Domains\Lead\Data\LeadCaptureData;
use App\Domains\Lead\Services\GatedAssetResolver;
use App\Domains\Lead\Services\LeadSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Capture-then-deliver for gated file downloads (acf/impact-report-hero).
 *
 * Production gates the 2026 Impact Report behind Gravity Forms form 33, which ADR-0008
 * retired. This is the replacement: the same name/email capture, but routed through the
 * Lead domain's CaptureLeadAction so a report download travels the same road as a
 * booking-wizard partial capture — LeadFormSubmitted, the Lead row, the dispatch log,
 * LeadCreated, and from there HubSpot, tracking and the admin notification. There is
 * deliberately no second lead path.
 *
 * What it does **not** raise is the sales fan-out: the Slack alert and the three outgoing
 * webhooks are for a lead somebody is about to phone, and this form collects no phone number
 * to do it with. See {@see self::capture()}.
 *
 * ASSUMPTION: production's post-submit behaviour lives in Gravity Forms' confirmation
 * settings, which are not visible without production admin access, so whether it redirects
 * to the file or emails a link is unknown. This implements capture-then-download (302 to
 * the file, or JSON for an XHR caller). If production turns out to email the link, the
 * delivery half changes here; the capture half does not.
 */
class GatedDownloadController
{
    /**
     * Abuse ceiling per client IP. An unauthenticated endpoint that writes a row and fans
     * out to HubSpot/Slack/webhooks on every hit is a spam target, so it gets the same
     * treatment as PaymentIntentController. Fails open on a cache error rather than
     * denying a real visitor their download.
     */
    protected const MAX_ATTEMPTS = 10;

    protected const DECAY_SECONDS = 60;

    public function __construct(
        protected CaptureLeadAction $captureLead,
        protected GatedAssetResolver $assets,
    ) {}

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        if ($this->tooManyAttempts($request)) {
            return $this->fail($request, 'Too many attempts. Please wait a moment and try again.', 429);
        }

        // Resolved from a slug against config/gated-assets.php — never from a posted URL.
        $asset = $this->assets->resolve($this->text($request->input('asset', '')));

        if ($asset === null) {
            Log::warning('GatedDownloadController: unknown or unresolvable asset', [
                'asset' => $this->text($request->input('asset', '')),
            ]);

            return $this->fail($request, 'That download is not available.', 404);
        }

        $name = $this->text($request->input('name', ''));
        $email = $this->email($request->input('email', ''));

        if ($name === '' || $email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->fail($request, 'Please enter your name and a valid email address.', 422);
        }

        $this->capture($request, $name, $email, $asset);

        if ($this->wantsJson($request)) {
            return response()->json([
                'ok' => true,
                'download_url' => $asset['url'],
                'title' => $asset['title'],
            ]);
        }

        return new RedirectResponse($asset['url']);
    }

    /**
     * Store the lead, and mark it as the kind of lead it is.
     *
     * `submission_type` is stamped through `attribution_named`, which is the channel
     * CaptureLeadAction actually writes to the row — `extra_data` reaches the LeadCreated
     * event's context and stops there. That distinction was the bug: this controller had been
     * putting `Gated Download` in `extra_data` since it was written, the column stayed null,
     * and a null submission type reads as a step-one sales capture everywhere downstream. The
     * team got a "New organic lead" card with a name and an email and nothing else on it —
     * no phone to call, no revenue band, no role — for somebody who had only asked for a PDF.
     *
     * Marking it is what {@see LeadSubmission::isSalesEnquiry()} then reads, so the Slack alert
     * and the three outgoing webhooks stay quiet. Everything else about the capture is
     * unchanged: the row, the activity log, HubSpot, tracking and the admin email all still
     * happen, because the lead is real — it is only sales that has nothing to act on yet.
     *
     * @param  array{slug: string, title: string, url: string}  $asset
     */
    protected function capture(Request $request, string $name, string $email, array $asset): void
    {
        try {
            $this->captureLead->execute(LeadCaptureData::fromArray([
                'name' => $name,
                'email' => $email,
                'referral_code' => $this->text($request->input('referral_code', '')) ?: null,
                'utm_source' => $this->text($request->input('utm_source', '')) ?: null,
                'utm_medium' => $this->text($request->input('utm_medium', '')) ?: null,
                'utm_campaign' => $this->text($request->input('utm_campaign', '')) ?: null,
                'utm_term' => $this->text($request->input('utm_term', '')) ?: null,
                'utm_content' => $this->text($request->input('utm_content', '')) ?: null,
                'gclid' => $this->text($request->input('gclid', '')) ?: null,
                'fbclid' => $this->text($request->input('fbclid', '')) ?: null,
                'landing_url' => $this->text($request->input('landing_url', '')) ?: null,
                'referrer_url' => (string) $request->headers->get('referer', '') ?: null,
                'session_id' => $this->text($request->input('session_id', '')) ?: null,
                'attribution_named' => [
                    'submission_type' => LeadSubmission::stored(LeadSubmission::GATED_DOWNLOAD),
                ],
                'extra_data' => [
                    'source_form' => 'ImpactReportHero',
                    'gated_asset' => $asset['slug'],
                    'gated_asset_title' => $asset['title'],
                ],
            ]));
        } catch (\Throwable $e) {
            // The visitor kept their side of the bargain; a CRM or DB failure is ours to
            // fix, not theirs to be denied the file over. Matches how the booking wizard
            // treats a failed partial capture.
            Log::error('GatedDownloadController: lead capture failed, delivering asset anyway', [
                'email' => $email,
                'asset' => $asset['slug'],
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * A JSON caller gets the real status code. A plain HTML form gets post/redirect/get back
     * to the page it came from with the message in the query string, because rendering an
     * error body here would strand the visitor on a bare /api/ URL.
     */
    protected function fail(Request $request, string $message, int $status): JsonResponse|RedirectResponse
    {
        if ($this->wantsJson($request)) {
            return response()->json(['ok' => false, 'error' => $message], $status);
        }

        $back = (string) $request->headers->get('referer', '') ?: '/';
        $separator = str_contains($back, '?') ? '&' : '?';

        return new RedirectResponse($back.$separator.'rl_download_error='.rawurlencode($message));
    }

    protected function wantsJson(Request $request): bool
    {
        return $request->ajax()
            || $request->wantsJson()
            || str_contains((string) $request->headers->get('accept', ''), 'application/json');
    }

    protected function tooManyAttempts(Request $request): bool
    {
        try {
            $key = 'rl-gated-download:'.($request->ip() ?? 'unknown');

            if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
                return true;
            }

            RateLimiter::hit($key, self::DECAY_SECONDS);
        } catch (\Throwable $e) {
            Log::warning('GatedDownloadController: rate limiter unavailable', ['error' => $e->getMessage()]);
        }

        return false;
    }

    protected function text(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        return function_exists('sanitize_text_field') ? sanitize_text_field($value) : trim(strip_tags($value));
    }

    protected function email(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        return function_exists('sanitize_email') ? sanitize_email($value) : trim($value);
    }
}
