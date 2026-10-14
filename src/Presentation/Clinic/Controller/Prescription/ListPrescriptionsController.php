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

/**
 * The register of ordonnances (ordonnances-v-topbar_3). The page module draws
 * the table from the payload and asks clinic_prescriptions_search for every
 * other page or filter.
 */
#[Route(path: '/prescriptions', name: 'clinic_prescriptions', methods: ['GET'])]
final class ListPrescriptionsController extends AbstractController
{
    public function __construct(
        private readonly PrescriptionPageBuilder $pages,
        private readonly CurrentClinicContextInterface $currentClinicContext,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        return $this->render('clinic/prescription/index.html.twig', [
            'payload' => $this->pages->page($currentClinicId->toString(), PrescriptionRegisterFilters::fromRequest($request)),
        ]);
    }
}
