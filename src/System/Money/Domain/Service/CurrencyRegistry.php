<?php

declare(strict_types=1);

namespace App\System\Money\Domain\Service;

use App\Shared\Domain\ValueObject\CurrencyCode;
use App\System\Money\Domain\Currency;

interface CurrencyRegistry
{
    public function get(CurrencyCode $code): Currency;

    /** @return list<Currency> */
    public function listActive(): array;

    public function has(CurrencyCode $code): bool;
}
