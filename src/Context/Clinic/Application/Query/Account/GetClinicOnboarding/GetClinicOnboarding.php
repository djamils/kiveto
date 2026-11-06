<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\Account\GetClinicOnboarding;

use App\Shared\Application\Bus\QueryInterface;

final readonly class GetClinicOnboarding implements QueryInterface
{
    public function __construct(
        public string $clinicId,
    ) {
    }
}
