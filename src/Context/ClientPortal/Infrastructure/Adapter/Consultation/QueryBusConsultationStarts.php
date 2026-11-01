<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Adapter\Consultation;

use App\Context\ClientPortal\Application\Port\ConsultationStartsInterface;
use App\Context\Consultation\Application\Query\ListAppointmentsWithConsultation\ListAppointmentsWithConsultation;
use App\Shared\Application\Bus\QueryBusInterface;

/** Consultation's ListAppointmentsWithConsultation. */
final readonly class QueryBusConsultationStarts implements ConsultationStartsInterface
{
    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function startedFrom(string $clinicId, array $appointmentIds): array
    {
        if ([] === $appointmentIds) {
            return [];
        }

        $ids = $this->queryBus->ask(new ListAppointmentsWithConsultation($clinicId, $appointmentIds));

        return \is_array($ids) ? array_values(array_filter($ids, \is_string(...))) : [];
    }
}
