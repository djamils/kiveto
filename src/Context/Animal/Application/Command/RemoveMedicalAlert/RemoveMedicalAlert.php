<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Command\RemoveMedicalAlert;

use App\Shared\Application\Bus\CommandInterface;

final readonly class RemoveMedicalAlert implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $animalId,
        public string $alertId,
    ) {
    }
}
