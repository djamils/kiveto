<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Query\GetConsultationDetails;

final readonly class ConsultationDetailsDTO
{
    /**
     * @param array{weightKg: ?string, temperatureC: ?string}|null              $vitals
     * @param list<array{noteType: string, content: string, createdAt: string}> $notes
     * @param list<array{label: string, quantity: string, performedAt: string}> $acts
     */
    public function __construct(
        public string $consultationId,
        public string $clinicId,
        public string $practitionerUserId,
        public string $status,
        public ?string $appointmentId,
        public ?string $waitingRoomEntryId,
        public ?string $ownerId,
        public ?string $animalId,
        public ?string $chiefComplaint,
        public ?array $vitals,
        public array $notes,
        public array $acts,
        public ?string $summary,
        public string $startedAtUtc,
        public ?string $closedAtUtc,
    ) {
    }
}
