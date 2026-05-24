<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Port;

use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;

interface ClinicInfoProviderInterface
{
    public function getCountryCode(ClinicId $clinicId): CountryCode;

    public function getCurrencyCode(ClinicId $clinicId): CurrencyCode;
}
