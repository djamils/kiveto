<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\Feature;

use App\Presentation\Backoffice\Feature\ConsoleFeatures;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * F2 « Coupe-circuit »: the feature off for every clinic at once, with its
 * reason, until « Remettre en service ».
 */
final class CutFeatureController extends AbstractController
{
    public function __construct(
        private readonly ConsoleFeatures $features,
    ) {
    }

    #[Route(path: '/features/{code}/cut', name: 'backoffice_feature_cut', requirements: ['code' => ListFeaturesController::CODE], methods: ['POST'])]
    public function __invoke(Request $request, string $code): RedirectResponse
    {
        if (!$this->isCsrfTokenValid(ShowFeatureController::CSRF_ID, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $feature = $this->features->find($code) ?? throw $this->createNotFoundException('Fonctionnalité inconnue.');

        try {
            $this->features->cut($feature, $request->request->getString('reason'));
        } catch (\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('backoffice_feature_show', ['code' => $code]);
        }

        $this->addFlash('success', \sprintf('« %s » est coupée partout.', $feature->name));

        return $this->redirectToRoute('backoffice_feature_show', ['code' => $code]);
    }
}
