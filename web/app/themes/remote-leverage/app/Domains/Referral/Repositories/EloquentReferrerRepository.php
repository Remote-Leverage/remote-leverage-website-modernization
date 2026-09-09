<?php

declare(strict_types=1);

namespace App\Domains\Referral\Repositories;

use App\Domains\Referral\Data\ReferrerData;
use App\Domains\Referral\Models\Referrer;
use Illuminate\Database\Eloquent\Collection;

class EloquentReferrerRepository implements ReferrerRepositoryInterface
{
    public function findById(int $id): ?Referrer
    {
        return Referrer::query()->find($id);
    }

    public function findByReferralCode(string $code): ?Referrer
    {
        return Referrer::query()->where('referral_code', $code)->first();
    }

    public function findByEmail(string $email): ?Referrer
    {
        return Referrer::query()->where('email', $email)->first();
    }

    public function create(ReferrerData $data): Referrer
    {
        return Referrer::query()->create($data->toArray());
    }

    public function update(Referrer $referrer, array $attributes): bool
    {
        return $referrer->update($attributes);
    }

    public function getActiveReferrers(): Collection
    {
        return Referrer::query()->active()->get();
    }
}
