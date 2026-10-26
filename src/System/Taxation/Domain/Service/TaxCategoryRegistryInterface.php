<?php

declare(strict_types=1);

namespace App\System\Taxation\Domain\Service;

use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use App\System\Taxation\Domain\ValueObject\TaxCategoryNature;

interface TaxCategoryRegistryInterface
{
    public function has(TaxCategoryCode $code): bool;

    /**
     * Goods or services; null when the category is unknown.
     */
    public function natureOf(TaxCategoryCode $code): ?TaxCategoryNature;
}
