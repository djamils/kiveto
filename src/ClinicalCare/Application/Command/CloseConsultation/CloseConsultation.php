<?php

declare(strict_types=1);

namespace App\ClinicalCare\Application\Command\CloseConsultation;

use App\Shared\Application\Bus\CommandInterface;

final readonly class CloseConsultation implements CommandInterface
{
    public function __construct(
        public string $consultationId,
        public string $closedByUserId,
        public ?string $summary,
    ) {
    }
}
