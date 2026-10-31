<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Query\GetLatestClosedConsultationWeight;

/**
 * A weight read from a consultation's vitals, dated by the consultation start.
 */
final readonly class ConsultationWeight
{
    public function __construct(
        public int $weightGrams,
        public \DateTimeImmutable $measuredAtUtc,
    ) {
    }
}
