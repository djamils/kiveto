<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Settings;

use App\Presentation\Clinic\ViewModel\Settings\SettingsPageBuilder;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Paramètres, section « Mes préférences »: what only concerns the member using the
 * page. Every member may open it, whatever their rights on the clinic.
 */
#[Route(path: '/settings/preferences', name: 'clinic_settings_preferences', methods: ['GET'])]
final class ShowClinicPreferencesController extends AbstractController
{
    public function __construct(
        private readonly SettingsPageBuilder $page,
        private readonly CurrentClinicContextInterface $currentClinicContext,
    ) {
    }

    public function __invoke(): Response
    {
        $clinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $clinicId);

        return $this->render('clinic/settings/page.html.twig', [
            'payload' => $this->page->page($clinicId->toString(), 'moi'),
        ]);
    }
}
