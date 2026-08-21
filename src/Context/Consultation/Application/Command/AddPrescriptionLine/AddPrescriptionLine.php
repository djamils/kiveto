<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Command\AddPrescriptionLine;

use App\Shared\Application\Bus\CommandInterface;

final readonly class AddPrescriptionLine implements CommandInterface
{
    public function __construct(
        public string $consultationId,
        public string $clinicId,
        public string $articleId,
        public ?string $dose,
        public ?string $frequency,
        public ?int $durationDays,
        public ?string $route,
        public float $quantity,
        public string $createdByUserId,
    ) {
    }
}
