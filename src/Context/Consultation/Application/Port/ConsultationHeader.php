<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Port;

use App\Context\Consultation\Domain\ValueObject\ConsultationStatus;

/**
 * A consultation's own row, without its record (notes, exam, plan, lines):
 * what a check on the consultation needs, in one statement.
 */
final readonly class ConsultationHeader
{
    public function __construct(
        public string $consultationId,
        public string $patientId,
        public string $practitionerUserId,
        public string $status,
        public string $startedAtUtc,
        public ?string $closedAtUtc,
    ) {
    }

    public function isClosed(): bool
    {
        return ConsultationStatus::CLOSED->value === $this->status;
    }
}
