<?php

declare(strict_types=1);

namespace App\System\Money\Domain\Repository;

use App\Shared\Domain\ValueObject\CurrencyCode;
use App\System\Money\Domain\Currency;

/**
 * Write repository for the Currency aggregate.
 *
 * findByCode() is exposed here (write repo) to support the idempotent upsert
 * in RegisterCurrencyHandler, consistent with ClinicRepositoryInterface::findById.
 */
interface CurrencyRepository
{
    public function save(Currency $currency): void;

    public function findByCode(CurrencyCode $code): ?Currency;
}
