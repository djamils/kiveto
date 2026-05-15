<?php

declare(strict_types=1);

namespace App\System\Taxation\Domain\Service;

use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;

interface TaxCategoryRegistryInterface
{
    public function has(TaxCategoryCode $code): bool;
}
