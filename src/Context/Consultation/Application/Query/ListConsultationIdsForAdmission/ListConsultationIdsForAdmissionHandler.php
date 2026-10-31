<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Query\ListConsultationIdsForAdmission;

use App\Context\Consultation\Application\Port\ConsultationReadRepositoryInterface;
use App\Context\Consultation\Domain\ValueObject\AdmissionId;
use App\Context\Consultation\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class ListConsultationIdsForAdmissionHandler
{
    public function __construct(private ConsultationReadRepositoryInterface $consultations)
    {
    }

    /**
     * @return list<string>
     */
    public function __invoke(ListConsultationIdsForAdmission $query): array
    {
        if (!Uuid::isValid($query->clinicId) || !Uuid::isValid($query->admissionId)) {
            return [];
        }

        return $this->consultations->consultationIdsForAdmission(
            ClinicId::fromString($query->clinicId),
            AdmissionId::fromString($query->admissionId),
        );
    }
}
