<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\Account\GetClinicAccount;

use App\Shared\Application\Bus\QueryInterface;

final readonly class GetClinicAccount implements QueryInterface
{
    public function __construct(
        public string $clinicId,
    ) {
    }
}
