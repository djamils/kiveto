<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Billing;

use App\Presentation\Clinic\ViewModel\Billing\BillingPageBuilder;
use App\Presentation\Clinic\ViewModel\Billing\BillingRegisterFilters;
use App\Presentation\Clinic\ViewModel\Billing\BillingRegisterViewBuilder;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * One page of the register for the filter bar, the pager and the search
 * field of the page module, as JSON.
 */
#[Route(path: '/invoices/search', name: 'clinic_invoices_search', methods: ['GET'])]
final class SearchInvoicesController extends AbstractController
{
    public function __construct(
        private readonly BillingRegisterViewBuilder $register,
        private readonly BillingPageBuilder $pages,
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
            BillingRegisterFilters::fromRequest($request),
            $this->pages->timeZone($clinicId),
        ));
    }
}
