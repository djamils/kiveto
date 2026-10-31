<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Adapter\Consultation;

use App\Context\ClientPortal\Application\Port\VisitSummariesInterface;
use App\Context\ClientPortal\Application\Port\VisitSummary;
use App\Context\Consultation\Application\Query\GetConsultationPortalSummaries\ConsultationPortalSummary;
use App\Context\Consultation\Application\Query\GetConsultationPortalSummaries\GetConsultationPortalSummaries;
use App\Shared\Application\Bus\QueryBusInterface;

/** Consultation's GetConsultationPortalSummaries: start and VisitTitle, in one call. */
final readonly class QueryBusVisitSummaries implements VisitSummariesInterface
{
    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function summariesOf(string $clinicId, array $consultationIds): array
    {
        if ([] === $consultationIds) {
            return [];
        }

        $rows = $this->queryBus->ask(new GetConsultationPortalSummaries($clinicId, $consultationIds));

        if (!\is_array($rows)) {
            return [];
        }

        $summaries = [];

        foreach ($rows as $row) {
            \assert($row instanceof ConsultationPortalSummary);
            $summaries[$row->consultationId] = new VisitSummary($row->consultationId, $row->startedAtUtc, $row->title);
        }

        return $summaries;
    }
}
