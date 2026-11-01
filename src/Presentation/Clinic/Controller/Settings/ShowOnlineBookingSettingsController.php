<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Settings;

use App\Presentation\Clinic\Security\ClinicPermissionGuard;
use App\Presentation\Clinic\ViewModel\Settings\SettingsPageBuilder;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Paramètres, section « Réservation en ligne »: whether the Portal takes
 * bookings, its slot grid and lead times, and the motifs offered. Same right
 * as the agenda (the clinic's administrators).
 */
#[Route(path: '/settings/online-booking', name: 'clinic_settings_online_booking', methods: ['GET'])]
final class ShowOnlineBookingSettingsController extends AbstractController
{
    public function __construct(
        private readonly SettingsPageBuilder $page,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly ClinicPermissionGuard $guard,
    ) {
    }

    public function __invoke(): Response
    {
        $this->guard->require(ClinicPermissionGuard::MANAGE_STAFF);

        $clinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $clinicId);

        return $this->render('clinic/settings/page.html.twig', [
            'payload' => $this->page->page($clinicId->toString(), 'reservation-en-ligne'),
        ]);
    }
}
