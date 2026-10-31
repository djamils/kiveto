<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\BackfillDocumentPets;

use App\Shared\Application\Bus\CommandInterface;

/** A patient now linked to an animal: its documents without pet get it. */
final readonly class BackfillDocumentPets implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $patientId,
        public string $animalId,
    ) {
    }
}
