<?php

declare(strict_types=1);

use App\Domains\Referral\Events\PayoutCompleted;
use App\Domains\Referral\Events\ReferralRecorded;
use App\Domains\Referral\Events\ReferrerRegistered;
use App\Domains\Referral\Listeners\HandleReferralEventsForSlack;
use App\Domains\Referral\Models\Payout;
use App\Domains\Referral\Models\Referral;
use App\Domains\Referral\Models\Referrer;
use Illuminate\Support\Str;

/*
 * The referral programme in Slack.
 *
 * All three of these events have been dispatched since the programme shipped and none of them
 * reached the channel — the only way to learn that a partner had signed up, sent someone, or
 * been paid was to open wp-admin and look. These tests are mostly about the two things that go
 * wrong when data is rendered for a sales channel: a slug leaking where a word belongs, and an
 * amount losing its currency.
 */

/** A referral listener that records what it would send. */
function referralSlackListener(): object
{
    return new class extends HandleReferralEventsForSlack
    {
        /** @var array<int, string> */
        public array $sent = [];

        /** @var array<int, array<int, array<string, mixed>>> */
        public array $sentBlocks = [];

        protected function send(string $text, array $blocks = [], ?string $color = null): ?array
        {
            $this->sent[] = $text;
            $this->sentBlocks[] = $blocks;

            return ['ts' => '1726500002.000300', 'channel' => 'C086BBKUXL5'];
        }
    };
}

function makeReferrer(array $attributes = []): Referrer
{
    return Referrer::query()->create(array_merge([
        'name' => 'Oyster Partners',
        'email' => 'partners-'.Str::random(6).'@oyster.example',
        'referral_code' => 'oyster-'.Str::random(4),
        'company' => 'Oyster HR',
        'status' => 'active',
    ], $attributes));
}

describe('referral alerts', function () {
    beforeEach(function () {
        config(['slack-notifications' => require __DIR__.'/../../config/slack-notifications.php']);
    });

    test('a new referrer is announced with their code', function () {
        $referrer = makeReferrer();

        $listener = referralSlackListener();
        $listener->handleReferrerRegistered(new ReferrerRegistered($referrer));

        $encoded = json_encode($listener->sentBlocks[0], JSON_UNESCAPED_SLASHES);

        expect($listener->sent[0])->toContain('Oyster Partners')
            ->and($encoded)->toContain('New referrer registered')
            ->and($encoded)->toContain($referrer->referral_code);
    });

    test('a referral names who sent it, because that is the whole point', function () {
        $referrer = makeReferrer(['name' => 'Jane Partner']);

        $referral = Referral::query()->create([
            'referrer_id' => $referrer->id,
            'lead_name' => 'Marcus Chen',
            'lead_email' => 'marcus@example.com',
            'lead_phone' => '+1 650 555 0123',
            'source' => 'portal',
            'status' => 'pending',
        ]);

        $listener = referralSlackListener();
        $listener->handleReferralRecorded(new ReferralRecorded($referral));

        $encoded = json_encode($listener->sentBlocks[0], JSON_UNESCAPED_SLASHES);

        expect($encoded)->toContain('Referral from Jane Partner')
            ->and($encoded)->toContain('Marcus Chen')
            ->and($encoded)->toContain('marcus@example.com');
    });

    test('a referral with no attributable referrer still posts', function () {
        // Older rows carry neither a referrer_id nor a user id. Dropping the alert would lose a
        // real referral; an empty name would read as a rendering bug.
        $referral = Referral::query()->create([
            'lead_name' => 'Unattributed Lead',
            'lead_email' => 'nobody@example.com',
            'status' => 'pending',
        ]);

        $listener = referralSlackListener();
        $listener->handleReferralRecorded(new ReferralRecorded($referral));

        expect($listener->sent)->toHaveCount(1)
            ->and(json_encode($listener->sentBlocks[0], JSON_UNESCAPED_SLASHES))
            ->toContain('Referral from Unknown referrer');
    });

    test('a payout names its currency', function () {
        // A bare number in a channel that also carries USD revenue bands is ambiguous exactly
        // when it matters.
        $referrer = makeReferrer(['name' => 'Jane Partner']);

        $payout = Payout::query()->create([
            'referrer_id' => $referrer->id,
            'amount' => 1250.00,
            'currency' => 'USD',
            'status' => 'paid',
            'stripe_transfer_id' => 'tr_12345',
            'referral_ids' => [1, 2, 3],
        ]);

        $listener = referralSlackListener();
        $listener->handlePayoutCompleted(new PayoutCompleted($payout));

        $encoded = json_encode($listener->sentBlocks[0], JSON_UNESCAPED_SLASHES);

        expect($encoded)->toContain('$1,250.00')
            ->and($encoded)->toContain('Jane Partner')
            ->and($encoded)->toContain('3 referrals')
            ->and($encoded)->toContain('tr_12345');
    });

    test('a non-USD payout says which currency, rather than looking like dollars', function () {
        $payout = Payout::query()->create([
            'referrer_id' => makeReferrer()->id,
            'amount' => 900.50,
            'currency' => 'eur',
            'status' => 'paid',
        ]);

        $listener = referralSlackListener();
        $listener->handlePayoutCompleted(new PayoutCompleted($payout));

        $encoded = json_encode($listener->sentBlocks[0], JSON_UNESCAPED_SLASHES);

        expect($encoded)->toContain('900.50 EUR')
            ->and($encoded)->not->toContain('$900.50');
    });

    test('statuses read as words, not as database slugs', function () {
        $referrer = makeReferrer(['status' => 'pending_review']);

        $listener = referralSlackListener();
        $listener->handleReferrerRegistered(new ReferrerRegistered($referrer));

        $encoded = json_encode($listener->sentBlocks[0], JSON_UNESCAPED_SLASHES);

        expect($encoded)->toContain('Pending review')
            ->and($encoded)->not->toContain('pending_review');
    });

    test('no emoji reaches Slack from any referral template', function () {
        // The house rule, asserted per family of templates rather than once, because each one
        // is pasted in from Block Kit Builder where the picker is one click away.
        $referrer = makeReferrer();

        $payout = Payout::query()->create([
            'referrer_id' => $referrer->id,
            'amount' => 10.00,
            'currency' => 'USD',
            'status' => 'paid',
        ]);

        $listener = referralSlackListener();
        $listener->handleReferrerRegistered(new ReferrerRegistered($referrer));
        $listener->handlePayoutCompleted(new PayoutCompleted($payout));

        $payload = implode('', $listener->sent)
            .json_encode($listener->sentBlocks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        expect(preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{2190}-\x{21FF}\x{25A0}-\x{25FF}]/u', $payload))
            ->toBe(0);
    });
});
