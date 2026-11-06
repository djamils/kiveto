<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\Feature;

use App\Presentation\Backoffice\Feature\ConsoleFeatures;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * F2 « Réglages globaux »: status, the clinics it is kept for, default for
 * new clinics; saved as soon as one changes.
 */
final class UpdateFeatureSettingsController extends AbstractController
{
    public function __construct(
        private readonly ConsoleFeatures $features,
    ) {
    }

    #[Route(path: '/features/{code}/settings', name: 'backoffice_feature_settings', requirements: ['code' => ListFeaturesController::CODE], methods: ['POST'])]
    public function __invoke(Request $request, string $code): RedirectResponse
    {
        if (!$this->isCsrfTokenValid(ShowFeatureController::CSRF_ID, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $feature = $this->features->find($code) ?? throw $this->createNotFoundException('Fonctionnalité inconnue.');

        try {
            $changes = $this->features->changeSettings(
                $feature,
                $request->request->getString('status'),
                $request->request->getBoolean('default_on'),
                $request->request->getString('audience', $feature->audience),
            );
        } catch (\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('backoffice_feature_show', ['code' => $code]);
        }

        $this->addFlash([] !== $changes ? 'success' : 'info', [] !== $changes ? 'Réglages enregistrés : ' . mb_strtolower(implode(', ', $changes)) . '.' : 'Rien n’a changé.');

        return $this->redirectToRoute('backoffice_feature_show', ['code' => $code]);
    }
}
