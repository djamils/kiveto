<?php

declare(strict_types=1);

namespace App\Context\Prescription\Application\Port;

/**
 * One medicine line of a consultation, as the Consultation BC records it.
 */
final readonly class ConsultationPrescriptionLineDto
{
    public function __construct(
        public string $sourceLineId,
        public ?string $articleId,
        public ?string $code,
        public string $label,
        public ?string $dose,
        public ?string $frequency,
        public ?int $durationDays,
        public ?string $route,
        public string $quantity,
    ) {
    }
}
