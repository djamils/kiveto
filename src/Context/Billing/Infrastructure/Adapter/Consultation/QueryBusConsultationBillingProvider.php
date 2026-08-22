<?php

declare(strict_types=1);

namespace App\Context\Billing\Infrastructure\Adapter\Consultation;

use App\Context\Billing\Application\Port\BillableLineDto;
use App\Context\Billing\Application\Port\ConsultationBillingProviderInterface;
use App\Context\Consultation\Application\Query\ListBillableLinesForAdmission\BillableLine;
use App\Context\Consultation\Application\Query\ListBillableLinesForAdmission\ListBillableLinesForAdmission;
use App\Shared\Application\Bus\QueryBusInterface;

final readonly class QueryBusConsultationBillingProvider implements ConsultationBillingProviderInterface
{
    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function billableLinesForAdmission(string $admissionId, string $clinicId): array
    {
        $result = $this->queryBus->ask(new ListBillableLinesForAdmission(
            clinicId: $clinicId,
            admissionId: $admissionId,
        ));

        if (!\is_array($result)) {
            return [];
        }

        $lines = [];

        foreach ($result as $line) {
            if (!$line instanceof BillableLine) {
                continue;
            }

            $lines[] = new BillableLineDto(
                consultationId: $line->consultationId,
                sourceLineId: $line->sourceLineId,
                label: $line->label,
                code: $line->code,
                quantity: $line->quantity,
                unitPriceMinorUnits: $line->unitPriceMinorUnits,
                currency: $line->currency,
                taxCategoryCode: $line->taxCategoryCode,
            );
        }

        return $lines;
    }
}
