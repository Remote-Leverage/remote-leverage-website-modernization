<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Domains\Marketing\Actions\SendCostAlertAction;
use App\Domains\Marketing\Support\CostAlertSendLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Where the cost alert card's "Send new alert" button lands.
 *
 * ## A page, then a POST
 *
 * The button is a link, so the press arrives as a GET — and a GET that posts to Slack is one that
 * anything fetching the URL would fire: a link preview, a browser warming a tab, a scanner. So the
 * GET only answers with a page, and the page's script POSTs the same signed values back to
 * {@see self::store()}, which does the work. It also means the tab shows something straight away
 * instead of a blank load for the fifteen seconds a send takes.
 *
 * ## Same send as the dashboard button
 *
 * `force: true`, exactly like `MarketingCostAlertWidget::handleSendAjax()`: the reporting window
 * and the environment gate exist to stop the *scheduler* posting when nobody asked, and a person
 * pressing a button has asked. A card from staging carries a staging link and posts a card with
 * the staging banner on it, which is the same thing that environment's dashboard button does.
 *
 * It does not check in with Sentry. The heartbeat watches whether the scheduler is alive, and a
 * person firing cards by hand is precisely the situation in which it must not be told it is.
 */
class CostAlertSendController
{
    /**
     * One card per press, however many times it is pressed.
     *
     * Held for the cooldown rather than released when the send ends, so a double click — or two
     * people pressing the same card a few seconds apart — posts one card and tells the second
     * press where to look. Released early only when the send fails, so a retry is not refused.
     */
    public const LOCK = 'rl_cost_alert_link_send';

    public const COOLDOWN_SECONDS = 120;

    public function __construct(private readonly SendCostAlertAction $action) {}

    public function show(Request $request): Response
    {
        $problem = CostAlertSendLink::check($request->query('expires'), $request->query('sig'));

        $html = view('pages.cost-alert-send', [
            'problem' => $problem,
            'message' => $problem === null ? '' : self::problemMessage($problem),
            // Echoed back only once they have verified, so the page never repeats anything else.
            'expires' => $problem === null ? (string) $request->query('expires') : '',
            'sig' => $problem === null ? (string) $request->query('sig') : '',
            'endpoint' => function_exists('home_url') ? (string) home_url('/api/marketing/cost-alert/send') : '',
            'dashboardUrl' => function_exists('admin_url') ? (string) admin_url('index.php') : '',
        ])->render();

        return new Response($html, $this->status($problem), self::privateHeaders());
    }

    public function store(Request $request): JsonResponse
    {
        $problem = CostAlertSendLink::check($request->input('expires'), $request->input('sig'));

        if ($problem !== null) {
            return response()->json([
                'sent' => false,
                'message' => self::problemMessage($problem),
            ], $this->status($problem), self::privateHeaders());
        }

        $lock = Cache::lock(self::LOCK, self::COOLDOWN_SECONDS);

        if (! $lock->get()) {
            return response()->json([
                'sent' => false,
                'message' => 'A fresh card was asked for in the last two minutes, so this press did not post another. Check the channel.',
            ], 429, self::privateHeaders());
        }

        try {
            $sent = $this->action->execute(force: true);
        } catch (\Throwable $e) {
            $lock->release();

            Log::error('CostAlertSendController: the send from the Slack button failed', [
                'error' => $e->getMessage(),
            ]);

            /*
             * Generic on purpose. The dashboard shows an admin the exception text; this endpoint
             * answers anyone holding a link, and an exception message is not something to hand
             * them.
             */
            return response()->json([
                'sent' => false,
                'message' => 'The send failed. The error is in the site log.',
            ], 500, self::privateHeaders());
        }

        if (! $sent) {
            $lock->release();

            return response()->json([
                'sent' => false,
                'message' => 'Nothing was sent. Slack rejected the message, or there was no card to render. The reason is in the site log.',
            ], 200, self::privateHeaders());
        }

        Log::info('CostAlertSendController: posted a fresh cost alert card from the Slack button', [
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'sent' => true,
            'message' => 'Posted. The new card is in the channel now.',
        ], 200, self::privateHeaders());
    }

    public static function problemMessage(string $problem): string
    {
        return $problem === CostAlertSendLink::EXPIRED
            ? 'This button has expired. Use the one on the latest card, or the dashboard.'
            : 'This link is not one the site recognises. Use the button on the latest card, or the dashboard.';
    }

    private function status(?string $problem): int
    {
        return match ($problem) {
            null => 200,
            CostAlertSendLink::EXPIRED => 410,
            default => 403,
        };
    }

    /**
     * Never cached, never indexed, and never sent onward as a referrer — the URL is the
     * permission, so it should not end up anywhere it was not put.
     *
     * @return array<string, string>
     */
    private static function privateHeaders(): array
    {
        return [
            'Cache-Control' => 'no-store, private',
            'X-Robots-Tag' => 'noindex, nofollow',
            'Referrer-Policy' => 'no-referrer',
        ];
    }
}
