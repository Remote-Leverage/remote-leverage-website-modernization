<?php

declare(strict_types=1);

namespace App\Domains\Referral\Repositories;

use App\Domains\Referral\Data\ReferrerData;
use App\Domains\Referral\Models\Referrer;
use Illuminate\Database\Eloquent\Collection;

interface ReferrerRepositoryInterface
{
    public function findById(int $id): ?Referrer;

    public function findByReferralCode(string $code): ?Referrer;

    public function findByEmail(string $email): ?Referrer;

    public function create(ReferrerData $data): Referrer;

    public function update(Referrer $referrer, array $attributes): bool;

    public function getActiveReferrers(): Collection;
}
