<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\PublishPrescription;

use App\Shared\Application\Bus\CommandInterface;

/** An issued prescription, shown to the owner it was written for. */
final readonly class PublishPrescription implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $prescriptionId,
    ) {
    }
}
