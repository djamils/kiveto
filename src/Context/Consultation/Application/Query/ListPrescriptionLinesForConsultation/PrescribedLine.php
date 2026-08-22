<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Query\ListPrescriptionLinesForConsultation;

/**
 * One medicine recorded on a consultation.
 */
final readonly class PrescribedLine
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
