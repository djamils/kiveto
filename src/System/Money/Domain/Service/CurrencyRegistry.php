<?php

declare(strict_types=1);

namespace App\System\Money\Domain\Service;

use App\Shared\Domain\ValueObject\CurrencyCode;
use App\System\Money\Domain\ValueObject\Currency;

/**
 * Read-only access to the static ISO 4217 currency catalogue.
 *
 * Domain interface implemented in Infrastructure (YamlCurrencyRegistry).
 * Lives in Domain/Service because MoneyCalculator depends on it directly —
 * moving it to Application/Port would force the domain to depend on a higher
 * layer, violating DDD layering rules.
 */
interface CurrencyRegistry
{
    public function get(CurrencyCode $code): Currency;

    /** @return list<Currency> */
    public function listAll(): array;

    public function has(CurrencyCode $code): bool;
}
