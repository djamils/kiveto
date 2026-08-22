<?php

declare(strict_types=1);

namespace App\Context\Prescription\Application\Query\GetPrescriptionDetails;

final readonly class RenewalView
{
    public function __construct(
        public string $id,
        public \DateTimeImmutable $renewedAt,
        public \DateTimeImmutable $newValidUntil,
    ) {
    }
}
