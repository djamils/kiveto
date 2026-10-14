<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Prescription;

use App\Presentation\Clinic\ViewModel\Prescription\PrescriptionPageBuilder;
use App\Presentation\Clinic\ViewModel\Prescription\PrescriptionRegisterFilters;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * One ordonnance: the register with its sheet open, over the whole register
 * (« Tout ») so that its row is there behind it — the mock-up's
 * `?ordonnance=` entry point. Every link to an ordonnance and every act done
 * on one comes back here.
 */
#[Route(
    path: '/prescriptions/{prescriptionId}',
    name: 'clinic_prescription_details',
    requirements: ['prescriptionId' => Requirement::UUID],
    methods: ['GET'],
)]
final class ShowPrescriptionController extends AbstractController
{
    public function __construct(
        private readonly PrescriptionPageBuilder $pages,
        private readonly CurrentClinicContextInterface $currentClinicContext,
    ) {
    }

    public function __invoke(Request $request, string $prescriptionId): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $clinicId = $currentClinicId->toString();
        $sheet    = $this->pages->sheet($clinicId, $prescriptionId);

        if (null === $sheet) {
            throw $this->createNotFoundException('Ordonnance introuvable.');
        }

        return $this->render('clinic/prescription/index.html.twig', [
            'payload' => $this->pages->page(
                $clinicId,
                PrescriptionRegisterFilters::fromRequest($request, $request->query->has('per') ? null : 'tout'),
                $sheet,
            ),
        ]);
    }
}
