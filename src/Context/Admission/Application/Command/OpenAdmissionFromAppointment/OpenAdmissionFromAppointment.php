<?php

declare(strict_types=1);

namespace App\Context\Admission\Application\Command\OpenAdmissionFromAppointment;

use App\Shared\Application\Bus\CommandInterface;

final readonly class OpenAdmissionFromAppointment implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $appointmentId,
        public ?string $knownAnimalId = null,
        public ?string $animalName = null,
        public ?string $triageNotes = null,
    ) {
    }
}
