<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Command\AddMedicalAlert;

use App\Shared\Application\Bus\CommandInterface;

final readonly class AddMedicalAlert implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $animalId,
        public string $kind,
        public string $label,
        public ?string $note = null,
    ) {
    }
}
