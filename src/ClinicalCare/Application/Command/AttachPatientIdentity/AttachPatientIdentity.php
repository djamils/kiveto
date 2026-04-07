<?php

declare(strict_types=1);

namespace App\ClinicalCare\Application\Command\AttachPatientIdentity;

use App\Shared\Application\Bus\CommandInterface;

final readonly class AttachPatientIdentity implements CommandInterface
{
    public function __construct(
        public string $consultationId,
        public ?string $ownerId,
        public ?string $animalId,
    ) {
    }
}
