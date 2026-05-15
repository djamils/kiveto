<?php

declare(strict_types=1);

namespace App\System\Taxation\Domain\Repository;

use App\System\Taxation\Domain\TaxCategory;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;

interface TaxCategoryRepositoryInterface
{
    public function save(TaxCategory $category): void;

    public function findByCode(TaxCategoryCode $code): ?TaxCategory;

    /** @return list<TaxCategory> */
    public function findAll(): array;

    /** @return list<TaxCategory> */
    public function findAllActive(): array;
}
