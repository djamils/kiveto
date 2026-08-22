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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    path: '/consultations/{consultationId}/report.pdf',
    name: 'clinic_consultation_report_pdf',
    methods: ['GET'],
)]
final class ConsultationReportPdfController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly ConsultationReportContext $reportContext,
        private readonly DocumentPdfFactory $documents,
    ) {
    }

    public function __invoke(string $consultationId): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        try {
            $report = $this->reportContext->build($consultationId, $currentClinicId->toString());
        } catch (\DomainException $exception) {
            throw $this->createNotFoundException('Consultation introuvable.', $exception);
        }

        $clinic = $this->queryBus->ask(new GetClinic($currentClinicId->toString()));
        $clinic = $clinic instanceof ClinicDto ? $clinic : null;

        $pdf = $this->documents->render(
            'clinic/document/report.html.twig',
            $report['context'],
            $clinic,
            'Compte rendu de consultation',
            $report['subject'],
        );

        return $this->documents->response($pdf, $report['filename']);
    }
}
