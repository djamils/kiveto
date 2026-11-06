<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\Feature;

use App\Presentation\Backoffice\Feature\ConsoleFeatures;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * F2 « Activer / Désactiver pour ces N »: the clinics the filters keep, at
 * once (those whose switch decides, not suspended).
 */
final class SwitchShownClinicsController extends AbstractController
{
    public function __construct(
        private readonly ConsoleFeatures $features,
    ) {
    }

    #[Route(path: '/features/{code}/clinics', name: 'backoffice_feature_bulk', requirements: ['code' => ListFeaturesController::CODE], methods: ['POST'])]
    public function __invoke(Request $request, string $code): RedirectResponse
    {
        if (!$this->isCsrfTokenValid(ShowFeatureController::CSRF_ID, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $feature = $this->features->find($code) ?? throw $this->createNotFoundException('Fonctionnalité inconnue.');
        $filters = ShowFeatureController::filters($request);
        $enabled = $request->request->getBoolean('enabled');
        $count   = $this->features->switchShown($feature, $enabled, $filters['q'], $filters['state'], $filters['plan']);

        $this->addFlash(0 < $count ? 'success' : 'info', 0 < $count
            ? \sprintf('« %s » %s pour %d clinique%s.', $feature->name, $enabled ? 'activée' : 'désactivée', $count, $count > 1 ? 's' : '')
            : 'Aucune clinique à changer.');

        $back = array_filter(['q' => '' !== $filters['q'] ? $filters['q'] : null, 'state' => 'all' !== $filters['state'] ? $filters['state'] : null, 'plan' => 'all' !== $filters['plan'] ? $filters['plan'] : null]);

        return $this->redirectToRoute('backoffice_feature_show', ['code' => $code] + $back);
    }
}
