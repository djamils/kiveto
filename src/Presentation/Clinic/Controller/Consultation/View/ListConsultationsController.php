<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Consultation\View;

use App\Presentation\Clinic\ViewModel\Consultation\ConsultationRegisterFilters;
use App\Presentation\Clinic\ViewModel\Consultation\ConsultationRegisterPageBuilder;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The register of consultations (consultations-v-topbar). The page module
 * draws the table from the payload and asks clinic_consultations_search for
 * every other page or filter. `?consultation=<id>` opens that consultation's
 * sheet over « Tout », the mock-up's entry point from other pages.
 */
#[Route(path: '/consultations', name: 'clinic_consultations', methods: ['GET'])]
final class ListConsultationsController extends AbstractController
{
    public function __construct(
        private readonly ConsultationRegisterPageBuilder $pages,
        private readonly CurrentClinicContextInterface $currentClinicContext,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $clinicId = $currentClinicId->toString();
        $asked    = $request->query->getString('consultation');
        $sheet    = '' !== $asked ? $this->pages->sheet($clinicId, $asked) : null;
        $period   = null !== $sheet && !$request->query->has('per') ? 'tout' : null;

        return $this->render('clinic/consultation/index.html.twig', [
            'payload' => $this->pages->page($clinicId, ConsultationRegisterFilters::fromRequest($request, $period), $sheet),
        ]);
    }
}
