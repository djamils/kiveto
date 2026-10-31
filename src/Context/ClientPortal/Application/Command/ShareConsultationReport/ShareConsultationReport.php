<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\ShareConsultationReport;

use App\Shared\Application\Bus\CommandInterface;

/**
 * The vet's « Partager sur le Portal » on a closed consultation. Returns a
 * ReportShareResult; throws ReportNotShareableException with its reason.
 */
final readonly class ShareConsultationReport implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $consultationId,
        public string $actorUserId,
    ) {
    }
}
