<?php

declare(strict_types=1);

namespace App\Context\Prescription\Application\Command\RenderPrescriptionFile;

use App\Shared\Application\Bus\CommandInterface;

/**
 * The PDF of an ordonnance written under the storage key the caller chose
 * (e.g. Consultation's documents e-mail, which keeps it for that send only).
 * Public: other contexts reach it through the command bus. Returns a
 * PrescriptionFile; writes nothing but the file, so a retry overwrites it.
 *
 * @throws \DomainException when the prescription is unknown to this clinic
 */
final readonly class RenderPrescriptionFile implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $prescriptionId,
        public string $storageKey,
    ) {
    }
}
