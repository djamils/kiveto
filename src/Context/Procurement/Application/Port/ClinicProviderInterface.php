<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Port;

use App\Context\Procurement\Domain\Shared\ValueObject\ClinicId;

interface ClinicProviderInterface
{
    /**
     * Returns ISO 4217 currency code for the clinic (e.g. 'EUR').
     */
    public function getCurrency(ClinicId $clinicId): string;
}
