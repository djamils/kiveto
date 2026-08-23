<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Settings;

use App\Context\Clinic\Application\Query\Clinic\GetClinicSettings\ClinicSettingsView;
use App\Context\Clinic\Application\Query\Clinic\GetClinicSettings\GetClinicSettings;
use App\Presentation\Clinic\Security\ClinicPermissionGuard;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Comment la clinique facture.
 */
#[Route(path: '/settings/billing', name: 'clinic_settings_billing', methods: ['GET'])]
final class ShowClinicBillingController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly ClinicPermissionGuard $guard,
    ) {
    }

    public function __invoke(): Response
    {
        $this->guard->require(ClinicPermissionGuard::MANAGE_BILLING);

        $clinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $clinicId);

        $settings = $this->queryBus->ask(new GetClinicSettings($clinicId->toString()));

        if (!$settings instanceof ClinicSettingsView) {
            throw $this->createNotFoundException('Clinique introuvable.');
        }

        return $this->render('clinic/settings/billing.html.twig', [
            'settings' => $settings,
        ]);
    }
}
