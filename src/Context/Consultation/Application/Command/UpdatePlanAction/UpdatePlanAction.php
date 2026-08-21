<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Command\UpdatePlanAction;

use App\Shared\Application\Bus\CommandInterface;

final readonly class UpdatePlanAction implements CommandInterface
{
    public function __construct(
        public string $consultationId,
        public string $clinicId,
        public string $planActionId,
        public string $description,
        public ?string $posology,
        public ?int $durationDays,
        public ?int $followUpDays,
        public float $quantity,
    ) {
    }
}
