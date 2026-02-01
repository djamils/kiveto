<?php

declare(strict_types=1);

namespace App\ClinicalCare\Application\Command\RecordVitals;

use App\Shared\Application\Bus\CommandInterface;

final readonly class RecordVitals implements CommandInterface
{
    public function __construct(
        public string $consultationId,
        public ?float $weightKg,
        public ?float $temperatureC,
    ) {
    }
}
