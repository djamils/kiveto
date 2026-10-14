<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Prescription;

use App\Presentation\Clinic\ViewModel\Prescription\PrescriptionPageBuilder;
use App\Presentation\Clinic\ViewModel\Prescription\PrescriptionRegisterFilters;
use App\Presentation\Clinic\ViewModel\Prescription\PrescriptionRegisterViewBuilder;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * One page of the register for the filter bar, the pager and the search
 * field of the page module, as JSON.
 */
#[Route(path: '/prescriptions/search', name: 'clinic_prescriptions_search', methods: ['GET'])]
final class SearchPrescriptionsController extends AbstractController
{
    public function __construct(
        private readonly PrescriptionRegisterViewBuilder $register,
        private readonly PrescriptionPageBuilder $pages,
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
            PrescriptionRegisterFilters::fromRequest($request),
            $this->pages->timeZone($clinicId),
        ));
    }
}
