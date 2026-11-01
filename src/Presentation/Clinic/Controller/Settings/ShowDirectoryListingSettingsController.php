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
 * Paramètres, section « Annuaire »: whether the clinic appears in the
 * Portal's clinic directory, takes new clients, and the tags on its card.
 * The clinic's administrators only.
 */
#[Route(path: '/settings/directory', name: 'clinic_settings_directory', methods: ['GET'])]
final class ShowDirectoryListingSettingsController extends AbstractController
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
            'payload' => $this->page->page($clinicId->toString(), 'annuaire'),
        ]);
    }
}
