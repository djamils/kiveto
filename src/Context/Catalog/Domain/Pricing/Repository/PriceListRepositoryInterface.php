<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Pricing\Repository;

use App\Context\Catalog\Domain\Pricing\PriceList;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceListId;
use App\Context\Catalog\Domain\ValueObject\ClinicId;

interface PriceListRepositoryInterface
{
    public function save(PriceList $priceList): void;

    public function findById(PriceListId $id, ClinicId $clinicId): ?PriceList;

    public function findDefaultForClinic(ClinicId $clinicId): ?PriceList;

    public function hasDefaultForClinic(ClinicId $clinicId): bool;
}
