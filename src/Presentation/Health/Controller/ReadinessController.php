<?php

declare(strict_types=1);

namespace App\Presentation\Health\Controller;

use App\Shared\Infrastructure\Health\ReadinessProbe;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The application can serve: database and Messenger queues reachable
 * (ReadinessProbe). 200 when every check passes, else 503; the body names
 * the checks only, the causes are in the logs.
 */
final readonly class ReadinessController
{
    public function __construct(private ReadinessProbe $probe)
    {
    }

    #[Route(path: '/health/ready', name: '_health_ready', methods: ['GET', 'HEAD'])]
    public function __invoke(): JsonResponse
    {
        $checks = $this->probe->check();
        $ready  = !\in_array(false, $checks, true);

        $response = new JsonResponse(
            ['status' => $ready ? 'ok' : 'unavailable', 'checks' => array_map(static fn (bool $passed): string => $passed ? 'ok' : 'fail', $checks)],
            $ready ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE,
        );
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
