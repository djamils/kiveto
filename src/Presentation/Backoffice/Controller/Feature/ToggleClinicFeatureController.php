<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\Feature;

use App\Presentation\Backoffice\Controller\Clinic\ShowClinicController;
use App\Presentation\Backoffice\Feature\ConsoleFeatures;
use App\Presentation\Backoffice\ViewModel\ConsoleDirectory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * A feature switched on or off for one clinic, from the feature's page or
 * from the clinic's sheet (`back=clinic`).
 */
final class ToggleClinicFeatureController extends AbstractController
{
    public function __construct(
        private readonly ConsoleFeatures $features,
        private readonly ConsoleDirectory $directory,
    ) {
    }

    #[Route(path: '/features/{code}/clinics/{clinicId}', name: 'backoffice_feature_toggle', requirements: ['code' => ListFeaturesController::CODE, 'clinicId' => ShowClinicController::UUID], methods: ['POST'])]
    public function __invoke(Request $request, string $code, string $clinicId): RedirectResponse
    {
        if (!$this->isCsrfTokenValid(ShowFeatureController::CSRF_ID, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $feature = $this->features->find($code) ?? throw $this->createNotFoundException('Fonctionnalité inconnue.');
        $clinic  = $this->directory->clinic($clinicId) ?? throw $this->createNotFoundException('Clinique introuvable.');
        $enabled = $request->request->getBoolean('enabled');
        $back    = 'clinic' === $request->request->getString('back')
            ? $this->redirectToRoute('backoffice_clinic_show', ['id' => $clinicId])
            : $this->redirectToRoute('backoffice_feature_show', ['code' => $code]);

        try {
            $changed = $this->features->toggle($clinic, $feature, $enabled);
        } catch (\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $back;
        }

        if ($changed) {
            $this->addFlash('success', \sprintf('« %s » %s pour %s.', $feature->name, $enabled ? 'activée' : 'désactivée', $clinic->name));
        }

        return $back;
    }
}
