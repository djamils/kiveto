<?php

declare(strict_types=1);

namespace App\Context\Prescription\Application\Command\RenderPrescriptionFile;

/**
 * An ordonnance PDF stored under the key its caller gave.
 */
final readonly class PrescriptionFile
{
    public const string MIME = 'application/pdf';

    /**
     * @param string $filename « ORD-2026-000391.pdf »
     */
    public function __construct(
        public string $storageKey,
        public string $filename,
    ) {
    }
}
