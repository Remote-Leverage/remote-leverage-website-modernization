<?php

declare(strict_types=1);

namespace App\Domains\Referral\Repositories;

use App\Domains\Referral\Data\PartnerData;
use App\Domains\Referral\Models\Partner;
use Illuminate\Database\Eloquent\Collection;

class EloquentPartnerRepository implements PartnerRepositoryInterface
{
    public function findById(int $id): ?Partner
    {
        return Partner::query()->find($id);
    }

    public function findByReferralCode(string $code): ?Partner
    {
        return Partner::query()->where('referral_code', $code)->first();
    }

    public function findByEmail(string $email): ?Partner
    {
        return Partner::query()->where('email', $email)->first();
    }

    public function create(PartnerData $data): Partner
    {
        return Partner::query()->create($data->toArray());
    }

    public function update(Partner $partner, array $attributes): bool
    {
        return $partner->update($attributes);
    }

    public function getActivePartners(): Collection
    {
        return Partner::query()->active()->get();
    }
}
