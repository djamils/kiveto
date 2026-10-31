<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Command\RenderConsultationReportFile;

use App\Shared\Application\Bus\CommandInterface;

/**
 * The PDF of a consultation report written under the storage key the caller
 * chose (e.g. ClientPortal's published documents, one key per version).
 * Public: other contexts reach it through the command bus. Returns a
 * ConsultationReportFile; writes nothing but the file, so a retry overwrites
 * it. The report reflects the consultation at render time (addenda included).
 *
 * @throws \DomainException when the consultation is unknown to this clinic
 */
final readonly class RenderConsultationReportFile implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $consultationId,
        public string $storageKey,
    ) {
    }
}
