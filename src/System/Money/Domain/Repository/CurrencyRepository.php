<?php

declare(strict_types=1);

namespace App\System\Money\Domain\Repository;

use App\Shared\Domain\ValueObject\CurrencyCode;
use App\System\Money\Domain\Currency;

interface CurrencyRepository
{
    public function save(Currency $currency): void;

    public function findByCode(CurrencyCode $code): ?Currency;
}
