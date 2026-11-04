<?php

declare(strict_types=1);

namespace App\Presentation\Health\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The application answers: no database, no session, on every host. Used by
 * the container healthchecks, the deploy script's smoke test and the
 * external probe.
 */
final readonly class LivenessController
{
    #[Route(path: '/health', name: '_health', methods: ['GET', 'HEAD'])]
    public function __invoke(): JsonResponse
    {
        $response = new JsonResponse(['status' => 'ok']);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
