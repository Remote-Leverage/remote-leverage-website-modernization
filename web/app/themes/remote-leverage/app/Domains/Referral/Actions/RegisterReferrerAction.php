<?php

declare(strict_types=1);

namespace App\Domains\Referral\Actions;

use App\Domains\Referral\Data\ReferrerData;
use App\Domains\Referral\Events\ReferrerRegistered;
use App\Domains\Referral\Models\Referrer;
use App\Domains\Referral\Repositories\ReferrerRepositoryInterface;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

class RegisterReferrerAction
{
    public function __construct(
        protected ReferrerRepositoryInterface $referrerRepository
    ) {}

    /**
     * Register a new referral-program referrer.
     *
     * @throws \InvalidArgumentException if no password is supplied
     */
    public function execute(array $input): Referrer
    {
        $email = strtolower(trim($input['email']));

        if (empty($input['password'])) {
            throw new \InvalidArgumentException('A password is required to register a referrer account.');
        }

        $existing = $this->referrerRepository->findByEmail($email);

        if ($existing) {
            /*
             * An account with no password is *unclaimed*, not taken. A sales rep creates these
             * from `/sales-referral` while the referrer is on the phone, so the referral can be
             * credited immediately — see createUnclaimed() below. This is where the person
             * themselves turns up later and takes ownership of it.
             *
             * The password check above deliberately moved in front of this lookup. It used to
             * return the existing row first, which meant someone signing up with an address a
             * rep had already used got handed that record with their chosen password silently
             * discarded — locked out of the one account holding their referrals, with no reset
             * flow to recover through.
             *
             * An account that already has a password is left exactly as it was: returning it
             * unchanged is right for a sign-up form, and overwriting it here would turn this
             * into a way to take over someone else's account by knowing their email.
             */
            if (empty($existing->password)) {
                $existing->forceFill([
                    'password' => password_hash($input['password'], PASSWORD_BCRYPT),
                    'name' => $existing->name ?: $input['name'],
                    'status' => 'active',
                ])->save();
            }

            return $existing;
        }

        $referralCode = $input['referral_code'] ?? null;
        if (! $referralCode) {
            $baseSlug = Str::slug($input['name']);
            $randomSuffix = strtolower(Str::random(4));
            $referralCode = $baseSlug ? "{$baseSlug}-{$randomSuffix}" : "referrer-{$randomSuffix}";
        }

        $referrerData = ReferrerData::fromArray([
            'name' => $input['name'],
            'email' => $email,
            'referral_code' => $referralCode,
            'company' => $input['company'] ?? null,
            'password' => password_hash($input['password'], PASSWORD_BCRYPT),
            'status' => 'active',
            'metadata' => $input['metadata'] ?? [],
        ]);

        $referrer = $this->referrerRepository->create($referrerData);

        Event::dispatch(new ReferrerRegistered($referrer));

        return $referrer;
    }

    /**
     * Create a referrer nobody has set a password on yet.
     *
     * For `/sales-referral`, where a rep registers someone who is on the phone. Two things
     * cannot happen on that call: the rep cannot choose the referrer's password, and there is
     * no way to send them one — there is no password-reset flow, and the welcome email carries
     * the referral link but no credentials.
     *
     * So the account is created *unclaimed*: a real referrer with a real code, able to be
     * credited with referrals from the moment it exists, but with no password and therefore no
     * portal login until the person signs up at `/referrer-register` with the same address and
     * claims it through `execute()` above. Attribution is the part with money attached, and it
     * does not have to wait for portal access.
     *
     * `status` is `pending` rather than `active` for exactly that reason — nobody has confirmed
     * they want this yet. Nothing in attribution filters on status (neither
     * `findByReferralCode()` nor `HandleLeadBookingCompletedForReferrer` look at it), so a
     * pending referrer is credited normally; the status is there to say which accounts are
     * waiting to be claimed.
     */
    public function createUnclaimed(array $input): Referrer
    {
        $email = strtolower(trim((string) $input['email']));
        $existing = $this->referrerRepository->findByEmail($email);

        if ($existing) {
            return $existing;
        }

        $referrer = $this->referrerRepository->create(ReferrerData::fromArray([
            'name' => $input['name'],
            'email' => $email,
            'referral_code' => $this->uniqueReferralCode((string) $input['name']),
            'company' => $input['company'] ?? null,
            'password' => null,
            'status' => 'pending',
            'metadata' => [
                'created_via' => $input['created_via'] ?? 'sales_rep',
                'unclaimed' => true,
            ],
        ]));

        Event::dispatch(new ReferrerRegistered($referrer));

        return $referrer;
    }

    /**
     * A referral code that is not already taken.
     *
     * `referral_code` is UNIQUE, so a collision is a raw query exception rather than anything a
     * rep mid-call could act on. `execute()`'s inline version above has never checked, which is
     * survivable for a self-service sign-up typing their own name and less so here, where two
     * reps could register the same company minutes apart.
     */
    protected function uniqueReferralCode(string $name): string
    {
        $base = Str::slug($name) ?: 'referrer';

        do {
            $code = $base.'-'.strtolower(Str::random(4));
        } while ($this->referrerRepository->findByReferralCode($code));

        return $code;
    }
}
