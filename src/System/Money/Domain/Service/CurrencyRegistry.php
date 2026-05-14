<?php

declare(strict_types=1);

namespace App\System\Money\Domain\Service;

use App\Shared\Domain\ValueObject\CurrencyCode;
use App\System\Money\Domain\Currency;

/**
 * Access to the catalogue of currencies known to the application.
 *
 * Domain interface implemented in Infrastructure (DoctrineCurrencyRegistry).
 * Lives in Domain/Service because MoneyCalculator depends on it directly —
 * moving it to Application/Port would force the domain to depend on a higher
 * layer, violating DDD layering rules.
 */
interface CurrencyRegistry
{
    public function get(CurrencyCode $code): Currency;

    /** @return list<Currency> */
    public function listActive(): array;

    public function has(CurrencyCode $code): bool;
}
