<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Query\ListBillableLinesForAdmission;

use App\Context\Consultation\Application\Port\ConsultationReadRepositoryInterface;
use App\Context\Consultation\Domain\ValueObject\AdmissionId;
use App\Context\Consultation\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * What a visit costs, line by line, across every consultation it holds.
 *
 * This is the seam the Billing BC reads through: it never touches the
 * consultation tables itself.
 */
#[AsMessageHandler]
final readonly class ListBillableLinesForAdmissionHandler
{
    public function __construct(private ConsultationReadRepositoryInterface $consultations)
    {
    }

    /**
     * @return list<BillableLine>
     */
    public function __invoke(ListBillableLinesForAdmission $query): array
    {
        $rows = $this->consultations->billingLinesForAdmission(
            ClinicId::fromString($query->clinicId),
            AdmissionId::fromString($query->admissionId),
        );

        return array_map(
            static fn (array $row): BillableLine => new BillableLine(
                consultationId: $row['consultationId'],
                sourceLineId: $row['sourceLineId'],
                label: $row['label'],
                code: $row['code'],
                quantity: $row['quantity'],
                unitPriceMinorUnits: $row['unitPriceMinorUnits'],
                currency: $row['currency'],
                taxCategoryCode: $row['taxCategoryCode'],
            ),
            $rows,
        );
    }
}
