<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Document;

use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinic;
use App\Context\Prescription\Application\Query\GetPrescriptionDetails\GetPrescriptionDetails;
use App\Context\Prescription\Application\Query\GetPrescriptionDetails\PrescriptionDetails;
use App\Presentation\Clinic\ViewModel\Document\DocumentPdfFactory;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/prescriptions/{prescriptionId}/pdf', name: 'clinic_prescription_pdf', methods: ['GET'])]
final class PrescriptionPdfController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly DocumentPdfFactory $documents,
    ) {
    }

    public function __invoke(string $prescriptionId): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        try {
            $prescription = $this->queryBus->ask(new GetPrescriptionDetails(
                clinicId: $currentClinicId->toString(),
                prescriptionId: $prescriptionId,
            ));
        } catch (\DomainException) {
            throw $this->createNotFoundException('Ordonnance introuvable.');
        }

        \assert($prescription instanceof PrescriptionDetails);

        $clinic = $this->queryBus->ask(new GetClinic($currentClinicId->toString()));
        $clinic = $clinic instanceof ClinicDto ? $clinic : null;

        $reference = \sprintf('Ordonnance · %s', $prescription->number ?? 'brouillon');

        $pdf = $this->documents->render(
            'clinic/document/prescription.html.twig',
            ['prescription' => $prescription],
            $clinic,
            $reference,
            \sprintf('%s / %s', $prescription->patientName, $prescription->ownerName),
        );

        return $this->documents->response($pdf, $prescription->number ?? 'ordonnance');
    }
}
