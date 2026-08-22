<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Query\ListPrescriptionLinesForConsultation;

use App\Context\Consultation\Application\Port\ConsultationReadRepositoryInterface;
use App\Context\Consultation\Domain\ValueObject\ClinicId;
use App\Context\Consultation\Domain\ValueObject\ConsultationId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * The medicines a consultation prescribed.
 *
 * This is the seam the Prescription BC reads through: it never touches the
 * consultation tables itself.
 */
#[AsMessageHandler]
final readonly class ListPrescriptionLinesForConsultationHandler
{
    public function __construct(private ConsultationReadRepositoryInterface $consultations)
    {
    }

    /**
     * @return list<PrescribedLine>
     */
    public function __invoke(ListPrescriptionLinesForConsultation $query): array
    {
        $rows = $this->consultations->prescriptionLinesForConsultation(
            ClinicId::fromString($query->clinicId),
            ConsultationId::fromString($query->consultationId),
        );

        return array_map(
            static fn (array $row): PrescribedLine => new PrescribedLine(
                sourceLineId: $row['sourceLineId'],
                articleId: $row['articleId'],
                code: $row['code'],
                label: $row['label'],
                dose: $row['dose'],
                frequency: $row['frequency'],
                durationDays: $row['durationDays'],
                route: $row['route'],
                quantity: $row['quantity'],
            ),
            $rows,
        );
    }
}
