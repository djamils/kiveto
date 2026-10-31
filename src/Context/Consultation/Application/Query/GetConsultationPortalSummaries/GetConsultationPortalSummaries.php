<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Query\GetConsultationPortalSummaries;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The start and the title of several consultations of a clinic, in one call.
 *
 * Feeds the Portal pet timeline: one row per shared report, dated by the
 * consultation start and titled by `VisitTitle`. No practitioner here — the
 * Portal shows the name snapshotted when the report was shared.
 */
final readonly class GetConsultationPortalSummaries implements QueryInterface
{
    /**
     * @param list<string> $consultationIds
     */
    public function __construct(
        public string $clinicId,
        public array $consultationIds,
    ) {
    }
}
