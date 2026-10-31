<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Query\GetConsultationPortalSummaries;

use App\Context\Consultation\Application\Port\ConsultationReadRepositoryInterface;
use App\Context\Consultation\Application\Service\VisitTitle;
use App\Context\Consultation\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetConsultationPortalSummariesHandler
{
    public function __construct(
        private ConsultationReadRepositoryInterface $consultations,
    ) {
    }

    /**
     * @return list<ConsultationPortalSummary> in the order of the requested ids;
     *                                         unknown ids and other clinics' consultations are left out
     */
    public function __invoke(GetConsultationPortalSummaries $query): array
    {
        if ([] === $query->consultationIds) {
            return [];
        }

        $rows = $this->consultations->portalSummaries(
            ClinicId::fromString($query->clinicId),
            $query->consultationIds,
        );

        return array_map(
            static fn (array $row): ConsultationPortalSummary => new ConsultationPortalSummary(
                consultationId: $row['consultationId'],
                startedAtUtc: new \DateTimeImmutable($row['startedAtUtc'], new \DateTimeZone('UTC')),
                title: VisitTitle::of($row['motifs'], $row['chiefComplaint']),
            ),
            $rows,
        );
    }
}
