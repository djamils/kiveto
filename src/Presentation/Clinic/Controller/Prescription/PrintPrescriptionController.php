<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Prescription;

use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinic;
use App\Context\Prescription\Application\Query\GetPrescriptionDetails\GetPrescriptionDetails;
use App\Context\Prescription\Application\Query\GetPrescriptionDetails\PrescriptionDetails;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/prescriptions/{prescriptionId}/print', name: 'clinic_prescription_print', methods: ['GET'])]
final class PrintPrescriptionController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
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

        return $this->render('clinic/prescription/print/index.html.twig', [
            'prescription' => $prescription,
            'clinic'       => $clinic instanceof ClinicDto ? $clinic : null,
        ]);
    }
}
