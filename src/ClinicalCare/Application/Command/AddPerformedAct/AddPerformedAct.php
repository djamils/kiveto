<?php

declare(strict_types=1);

namespace App\ClinicalCare\Application\Command\AddPerformedAct;

use App\Shared\Application\Bus\CommandInterface;

final readonly class AddPerformedAct implements CommandInterface
{
    public function __construct(
        public string $consultationId,
        public string $label,
        public float $quantity,
        public string $performedAt, // ISO-8601
        public string $createdByUserId,
    ) {
    }
}
