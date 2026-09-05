<?php

declare(strict_types=1);

namespace App\Domains\Referral\Actions;

use App\Domains\Referral\Data\PartnerData;
use App\Domains\Referral\Events\PartnerRegistered;
use App\Domains\Referral\Models\Partner;
use App\Domains\Referral\Repositories\PartnerRepositoryInterface;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

class RegisterPartnerAction
{
    public function __construct(
        protected PartnerRepositoryInterface $partnerRepository
    ) {}

    /**
     * Register a new affiliate partner.
     */
    public function execute(array $input): Partner
    {
        $email = strtolower(trim($input['email']));

        $existing = $this->partnerRepository->findByEmail($email);
        if ($existing) {
            return $existing;
        }

        $referralCode = $input['referral_code'] ?? null;
        if (! $referralCode) {
            $baseSlug = Str::slug($input['name']);
            $randomSuffix = strtolower(Str::random(4));
            $referralCode = $baseSlug ? "{$baseSlug}-{$randomSuffix}" : "partner-{$randomSuffix}";
        }

        $partnerData = PartnerData::fromArray([
            'name' => $input['name'],
            'email' => $email,
            'referral_code' => $referralCode,
            'company' => $input['company'] ?? null,
            'status' => 'active',
            'metadata' => $input['metadata'] ?? [],
        ]);

        $partner = $this->partnerRepository->create($partnerData);

        Event::dispatch(new PartnerRegistered($partner));

        return $partner;
    }
}
