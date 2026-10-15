<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Document;

use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinic;
use App\Presentation\Clinic\ViewModel\Document\ConsultationReportContext;
use App\Presentation\Clinic\ViewModel\Document\DocumentPdfFactory;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * The register's bulk bar: the reports of the selected consultations in one
 * PDF. Only a closed consultation has a signed report; the others, and those
 * of another clinic, are left out.
 */
#[Route(path: '/consultations/reports.pdf', name: 'clinic_consultations_reports_pdf', methods: ['GET'])]
final class ConsultationReportsSelectionPdfController extends AbstractController
{
    private const int MAX_DOCUMENTS = 100;

    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly ConsultationReportContext $reportContext,
        private readonly DocumentPdfFactory $documents,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $clinicId = $currentClinicId->toString();
        $clinic   = $this->queryBus->ask(new GetClinic($clinicId));
        $clinic   = $clinic instanceof ClinicDto ? $clinic : null;
        $parts    = [];

        foreach (\array_slice($this->ids($request), 0, self::MAX_DOCUMENTS) as $id) {
            try {
                $report = $this->reportContext->build($id, $clinicId);
            } catch (\DomainException) {
                continue;
            }

            if ('CLOSED' !== $report['consultation']->status) {
                continue;
            }

            $parts[] = $this->documents->render(
                'clinic/document/report.html.twig',
                $report['context'],
                $clinic,
                'Compte rendu de consultation',
                $report['subject'],
            );
        }

        if ([] === $parts) {
            throw $this->createNotFoundException('Aucune consultation clôturée dans la sélection.');
        }

        return $this->documents->response(
            1 === \count($parts) ? $parts[0] : $this->documents->merge($parts),
            \sprintf('comptes-rendus-%d', \count($parts)),
        );
    }

    /**
     * @return list<string>
     */
    private function ids(Request $request): array
    {
        $ids = [];

        foreach ($request->query->all('ids') as $id) {
            if (\is_string($id) && Uuid::isValid($id) && !\in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
