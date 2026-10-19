<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Settings;

use App\Presentation\Clinic\Security\ClinicPermissionGuard;
use App\Presentation\Clinic\ViewModel\Settings\SettingsPageBuilder;
use App\Presentation\Clinic\ViewModel\Shared\ReturnLink;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Paramètres, section « Facturation et caisse »: how the clinic numbers its invoices,
 * what it takes and what it prints at their foot.
 */
#[Route(path: '/settings/billing', name: 'clinic_settings_billing', methods: ['GET'])]
final class ShowClinicBillingController extends AbstractController
{
    public function __construct(
        private readonly SettingsPageBuilder $page,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly ClinicPermissionGuard $guard,
        private readonly ReturnLink $returnLink,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $this->guard->require(ClinicPermissionGuard::MANAGE_BILLING);

        $clinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $clinicId);

        return $this->render('clinic/settings/page.html.twig', [
            'payload' => $this->page->page($clinicId->toString(), 'facturation'),
            // Opened from a refused checkout: the way back to it.
            'returnLink' => $this->returnLink->from($request->query->getString('_returnTo')),
        ]);
    }
}
