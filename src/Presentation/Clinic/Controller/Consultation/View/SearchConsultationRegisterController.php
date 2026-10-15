<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Consultation\View;

use App\Presentation\Clinic\ViewModel\Consultation\ConsultationRegisterFilters;
use App\Presentation\Clinic\ViewModel\Consultation\ConsultationRegisterPageBuilder;
use App\Presentation\Clinic\ViewModel\Consultation\ConsultationRegisterViewBuilder;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * One page of the register for the filter bar, the pager and the search
 * field of the page module, as JSON.
 */
#[Route(path: '/consultations/search', name: 'clinic_consultations_search', methods: ['GET'])]
final class SearchConsultationRegisterController extends AbstractController
{
    public function __construct(
        private readonly ConsultationRegisterViewBuilder $register,
        private readonly ConsultationRegisterPageBuilder $pages,
        private readonly CurrentClinicContextInterface $currentClinicContext,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $clinicId = $currentClinicId->toString();

        return new JsonResponse($this->register->register(
            $clinicId,
            ConsultationRegisterFilters::fromRequest($request),
            $this->pages->timeZone($clinicId),
        ));
    }
}
