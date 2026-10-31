<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\RepointDocumentPatient;

use App\Shared\Application\Bus\CommandInterface;

/** A provisional patient was merged into another: its documents follow. */
final readonly class RepointDocumentPatient implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $sourcePatientId,
        public string $targetPatientId,
    ) {
    }
}
