<?php

declare(strict_types=1);

namespace App\Domains\Referral\Repositories;

use App\Domains\Referral\Data\PartnerData;
use App\Domains\Referral\Models\Partner;
use Illuminate\Database\Eloquent\Collection;

interface PartnerRepositoryInterface
{
    public function findById(int $id): ?Partner;

    public function findByReferralCode(string $code): ?Partner;

    public function findByEmail(string $email): ?Partner;

    public function create(PartnerData $data): Partner;

    public function update(Partner $partner, array $attributes): bool;

    public function getActivePartners(): Collection;
}
