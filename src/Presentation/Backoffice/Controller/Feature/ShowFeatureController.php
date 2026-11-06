<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\Feature;

use App\Presentation\Backoffice\Feature\ConsoleFeatures;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * F2 / F3 — a feature: its clinics (toggles), its global settings, its
 * history, and « Activer pour toutes ».
 */
final class ShowFeatureController extends AbstractController
{
    public const string CSRF_ID = 'backoffice_feature';

    public function __construct(
        private readonly ConsoleFeatures $features,
    ) {
    }

    #[Route(path: '/features/{code}', name: 'backoffice_feature_show', requirements: ['code' => ListFeaturesController::CODE], methods: ['GET'])]
    public function __invoke(Request $request, string $code): Response
    {
        $detail = $this->features->detail($code);

        if (null === $detail) {
            throw $this->createNotFoundException('Fonctionnalité inconnue.');
        }

        return $this->render('backoffice/console/features/show.html.twig', [
            'detail'      => $detail,
            'badges'      => ConsoleFeatures::BADGES,
            'statuses'    => ['available' => 'Disponible', 'beta' => 'Bêta', 'internal' => 'Interne'],
            'open_dialog' => 'all' === $request->query->getString('dialog') && [] !== $detail['missing'] ? 'dialog-all' : '',
        ]);
    }
}
