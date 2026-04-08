<?php

declare(strict_types=1);

namespace App\Context\ClinicalCare\Application\Command\RecordChiefComplaint;

use App\Shared\Application\Bus\CommandInterface;

final readonly class RecordChiefComplaint implements CommandInterface
{
    public function __construct(
        public string $consultationId,
        public string $chiefComplaint,
    ) {
    }
}
