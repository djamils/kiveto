<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\Feature;

use App\Presentation\Backoffice\Feature\ConsoleFeatures;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * F3 — « Activer pour toutes »: every clinic not suspended gets the feature.
 */
final class EnableFeatureForAllController extends AbstractController
{
    public function __construct(
        private readonly ConsoleFeatures $features,
    ) {
    }

    #[Route(path: '/features/{code}/enable-all', name: 'backoffice_feature_enable_all', requirements: ['code' => ListFeaturesController::CODE], methods: ['POST'])]
    public function __invoke(Request $request, string $code): RedirectResponse
    {
        if (!$this->isCsrfTokenValid(ShowFeatureController::CSRF_ID, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $feature = $this->features->find($code) ?? throw $this->createNotFoundException('Fonctionnalité inconnue.');

        if ($feature->isUpcoming()) {
            $this->addFlash('error', 'Une fonctionnalité « À venir » ne s’active pas : son module n’existe pas encore.');

            return $this->redirectToRoute('backoffice_feature_show', ['code' => $code]);
        }

        $count = $this->features->enableForAll($feature);
        $this->addFlash('success', 0 < $count ? \sprintf('« %s » activée pour %d clinique%s.', $feature->name, $count, $count > 1 ? 's' : '') : 'Toutes les cliniques l’ont déjà.');

        return $this->redirectToRoute('backoffice_feature_show', ['code' => $code]);
    }
}
