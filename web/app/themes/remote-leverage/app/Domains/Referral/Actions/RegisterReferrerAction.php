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

        $existing = $this->referrerRepository->findByEmail($email);
        if ($existing) {
            return $existing;
        }

        if (empty($input['password'])) {
            throw new \InvalidArgumentException('A password is required to register a referrer account.');
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
}
