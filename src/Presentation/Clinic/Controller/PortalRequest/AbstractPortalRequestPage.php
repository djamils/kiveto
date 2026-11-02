<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\PortalRequest;

use App\Presentation\Clinic\ViewModel\PortalRequest\PortalRequestPageBuilder;
use App\Presentation\Clinic\ViewModel\PortalRequest\PortalRequestRegisterFilters;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The « Demandes » page shell: the page module draws the register from the
 * payload and asks clinic_portal_requests_search for every other page or
 * filter, a sheet from its sheet route. The list route and the three
 * request routes render it — a request's URL with its sheet open over the
 * list; a request opened without filters (the bell, the dashboard) shows
 * the list of its own state.
 */
abstract class AbstractPortalRequestPage extends AbstractController
{
    /**
     * @param ?array<string, mixed> $sheet PortalRequestPageBuilder::requestSheet(), linkSheet() or bookingSheet()
     */
    protected function renderPage(PortalRequestPageBuilder $pages, Request $request, string $clinicId, ?array $sheet = null): Response
    {
        $filters = PortalRequestRegisterFilters::fromRequest($request, null === $sheet ? PortalRequestRegisterFilters::DEFAULT_STATUS : PortalRequestPageBuilder::ownStatus($sheet));

        return $this->render('clinic/portal_requests/index.html.twig', [
            'payload' => $pages->page($clinicId, $filters, $sheet),
        ]);
    }
}
