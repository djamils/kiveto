<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Command\AddPlanAction;

use App\Shared\Application\Bus\CommandInterface;

final readonly class AddPlanAction implements CommandInterface
{
    public function __construct(
        public string $consultationId,
        public string $clinicId,
        public string $kind,
        public string $description,
        public ?string $catalogItemId,
        public ?string $catalogCode,
        public ?string $posology,
        public ?int $durationDays,
        public ?int $followUpDays,
        public float $quantity,
        public string $createdByUserId,
    ) {
    }
}
