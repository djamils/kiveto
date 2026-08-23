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
 * Who the clinic is: the name it goes by and the identity it prints.
 */
#[Route(path: '/settings', name: 'clinic_settings', methods: ['GET'])]
final class ShowClinicIdentityController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly ClinicPermissionGuard $guard,
    ) {
    }

    public function __invoke(): Response
    {
        $this->guard->require(ClinicPermissionGuard::MANAGE_STAFF);

        $clinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $clinicId);

        $settings = $this->queryBus->ask(new GetClinicSettings($clinicId->toString()));

        if (!$settings instanceof ClinicSettingsView) {
            throw $this->createNotFoundException('Clinique introuvable.');
        }

        return $this->render('clinic/settings/identity.html.twig', [
            'settings' => $settings,
        ]);
    }
}
