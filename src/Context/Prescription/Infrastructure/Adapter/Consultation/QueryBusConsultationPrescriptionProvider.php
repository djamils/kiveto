<?php

declare(strict_types=1);

namespace App\Context\Prescription\Infrastructure\Adapter\Consultation;

use App\Context\Consultation\Application\Query\ListPrescriptionLinesForConsultation\ListPrescriptionLinesForConsultation;
use App\Context\Consultation\Application\Query\ListPrescriptionLinesForConsultation\PrescribedLine;
use App\Context\Prescription\Application\Port\ConsultationPrescriptionLineDto;
use App\Context\Prescription\Application\Port\ConsultationPrescriptionProviderInterface;
use App\Shared\Application\Bus\QueryBusInterface;

final readonly class QueryBusConsultationPrescriptionProvider implements ConsultationPrescriptionProviderInterface
{
    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function linesForConsultation(string $consultationId, string $clinicId): array
    {
        $result = $this->queryBus->ask(new ListPrescriptionLinesForConsultation(
            clinicId: $clinicId,
            consultationId: $consultationId,
        ));

        if (!\is_array($result)) {
            return [];
        }

        $lines = [];

        foreach ($result as $line) {
            if (!$line instanceof PrescribedLine) {
                continue;
            }

            $lines[] = new ConsultationPrescriptionLineDto(
                sourceLineId: $line->sourceLineId,
                articleId: $line->articleId,
                code: $line->code,
                label: $line->label,
                dose: $line->dose,
                frequency: $line->frequency,
                durationDays: $line->durationDays,
                route: $line->route,
                quantity: $line->quantity,
            );
        }

        return $lines;
    }
}
