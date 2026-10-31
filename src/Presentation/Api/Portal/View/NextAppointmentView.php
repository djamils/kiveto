<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\View;

use ApiPlatform\Metadata\ApiProperty;
use App\Context\ClientPortal\Application\Query\GetOwnerHome\NextAppointment;
use App\Presentation\Api\Portal\Support\ApiDate;

/** The slice-3 shape of the home's next appointment: never the free-text reason. */
final readonly class NextAppointmentView
{
    public function __construct(
        public string $id,
        #[ApiProperty(schema: ['type' => 'string', 'format' => 'date-time'])]
        public string $startAt,
        public PetReferenceView $pet,
        public ?string $practitioner,
        public string $motifLabel,
    ) {
    }

    public static function of(NextAppointment $appointment): self
    {
        return new self(
            $appointment->id,
            ApiDate::formatLocal($appointment->startAt),
            new PetReferenceView($appointment->petId, $appointment->petName),
            $appointment->practitioner,
            $appointment->motifLabel,
        );
    }
}
