<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Prescription;

use App\Context\Prescription\Application\Query\SearchPrescriptions\PrescriptionListItemView;
use App\Context\Prescription\Application\Query\SearchPrescriptions\PrescriptionListLine;
use App\Context\Prescription\Application\Query\SearchPrescriptions\SearchPrescriptions;
use App\Context\Prescription\Application\Query\SearchPrescriptions\SearchPrescriptionsCriteria;
use App\Presentation\Clinic\ViewModel\Prescription\PrescriptionPageBuilder;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Exporter le registre des ordonnances »: every signed ordonnance in number
 * order, the cancelled ones included, as a CSV the spreadsheet opens.
 */
#[Route(path: '/prescriptions/export.csv', name: 'clinic_prescriptions_export', methods: ['GET'])]
final class ExportPrescriptionRegisterController extends AbstractController
{
    private const int BATCH = 100;

    private const array STATUS_LABELS = ['ISSUED' => 'Émise', 'CANCELLED' => 'Annulée'];

    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly PrescriptionPageBuilder $pages,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $clinicId = $currentClinicId->toString();
        $tz       = $this->pages->timeZone($clinicId);
        $now      = $this->clock->now();
        $out      = fopen('php://temp', 'r+');
        \assert(false !== $out);

        // A BOM so that the spreadsheet reads the accents as UTF-8.
        fwrite($out, "\u{FEFF}");
        fputcsv($out, ['Numéro', 'Date', 'Statut', 'Animal', 'Détenteur', 'Prescripteur', 'Médicaments', 'Valable jusqu\'au'], ';', '"', '');

        $page = 1;

        do {
            $view = $this->queryBus->ask(new SearchPrescriptions($clinicId, new SearchPrescriptionsCriteria(
                statuses: ['ISSUED', 'CANCELLED'],
                sortBy: SearchPrescriptionsCriteria::SORT_NUMBER,
                sortDescending: false,
                page: $page,
                perPage: self::BATCH,
            )));
            \assert($view instanceof PrescriptionListItemView);

            foreach ($view->items as $row) {
                $expired = 'ISSUED' === $row->status && $row->isExpiredAt($now);

                fputcsv($out, [
                    $row->number ?? '',
                    ($row->issuedAt ?? $row->createdAt)->setTimezone($tz)->format('d/m/Y H:i'),
                    $expired ? 'Expirée' : (self::STATUS_LABELS[$row->status] ?? $row->status),
                    $row->patientName,
                    $row->ownerName,
                    $row->prescriberName,
                    implode(' ; ', array_map(static fn (PrescriptionListLine $line): string => $line->label, $row->lines)),
                    $row->validUntil?->setTimezone($tz)->format('d/m/Y') ?? '',
                ], ';', '"', '');
            }

            ++$page;
        } while ($page <= $view->pageCount());

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return new Response(false !== $csv ? $csv : '', Response::HTTP_OK, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => \sprintf('attachment; filename="registre-ordonnances-%s.csv"', $now->setTimezone($tz)->format('Y-m-d')),
            'Cache-Control'       => 'private, no-store',
        ]);
    }
}
