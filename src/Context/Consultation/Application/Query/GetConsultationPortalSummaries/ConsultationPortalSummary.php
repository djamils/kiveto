<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Query\GetConsultationPortalSummaries;

final readonly class ConsultationPortalSummary
{
    public function __construct(
        public string $consultationId,
        public \DateTimeImmutable $startedAtUtc,
        public string $title,
    ) {
    }
}
