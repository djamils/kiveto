<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Adapter\Consultation;

use App\Context\ClientPortal\Application\Port\ConsultationWeightsInterface;
use App\Context\ClientPortal\Application\Port\VisitWeight;
use App\Context\Consultation\Application\Query\GetLatestClosedConsultationWeight\ConsultationWeight;
use App\Context\Consultation\Application\Query\GetLatestClosedConsultationWeight\GetLatestClosedConsultationWeight;
use App\Shared\Application\Bus\QueryBusInterface;

/** Consultation's GetLatestClosedConsultationWeight (every patient record of the animal). */
final readonly class QueryBusConsultationWeights implements ConsultationWeightsInterface
{
    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function latestClosedOf(string $clinicId, string $animalId): ?VisitWeight
    {
        $weight = $this->queryBus->ask(new GetLatestClosedConsultationWeight($clinicId, $animalId));

        return $weight instanceof ConsultationWeight ? new VisitWeight($weight->weightGrams, $weight->measuredAtUtc) : null;
    }
}
