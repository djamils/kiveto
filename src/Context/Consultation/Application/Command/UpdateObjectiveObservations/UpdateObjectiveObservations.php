<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Command\UpdateObjectiveObservations;

use App\Shared\Application\Bus\CommandInterface;

final readonly class UpdateObjectiveObservations implements CommandInterface
{
    public function __construct(
        public string $consultationId,
        public string $clinicId,
        public ?string $text,
    ) {
    }
}
