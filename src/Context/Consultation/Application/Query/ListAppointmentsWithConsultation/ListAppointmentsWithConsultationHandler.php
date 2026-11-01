<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Query\ListAppointmentsWithConsultation;

use App\Context\Consultation\Application\Port\ConsultationReadRepositoryInterface;
use App\Context\Consultation\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class ListAppointmentsWithConsultationHandler
{
    public function __construct(private ConsultationReadRepositoryInterface $consultations)
    {
    }

    /**
     * @return list<string>
     */
    public function __invoke(ListAppointmentsWithConsultation $query): array
    {
        $ids = array_values(array_filter($query->appointmentIds, Uuid::isValid(...)));

        if (!Uuid::isValid($query->clinicId) || [] === $ids) {
            return [];
        }

        return $this->consultations->appointmentIdsWithConsultation(ClinicId::fromString($query->clinicId), $ids);
    }
}
