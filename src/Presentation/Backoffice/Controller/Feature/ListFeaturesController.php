<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\Feature;

use App\Presentation\Backoffice\Feature\ConsoleFeatures;
use App\Presentation\Backoffice\ViewModel\ConsoleDirectory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * F1 — « Fonctionnalités »: the catalogue, each feature with its status,
 * its default and the clinics that have it.
 */
final class ListFeaturesController extends AbstractController
{
    public const string CODE = '[a-z_]+\.[a-z_]+';

    public function __construct(
        private readonly ConsoleFeatures $features,
        private readonly ConsoleDirectory $directory,
    ) {
    }

    #[Route(path: '/features', name: 'backoffice_features', methods: ['GET'])]
    public function __invoke(): Response
    {
        $features = $this->features->list();

        return $this->render('backoffice/console/features/index.html.twig', [
            'features'  => $features,
            'clinics'   => \count($this->directory->clinics()),
            'available' => \count(array_filter($features, static fn ($f): bool => !$f->isUpcoming())),
            'badges'    => ConsoleFeatures::BADGES,
        ]);
    }
}
