<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Problem;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * API Platform names its validation errors `/validation_errors/<codes>`; the
 * Portal API's contract has one code for them all, `validation_failed`,
 * next to the native `violations[]`.
 */
final readonly class ValidationProblemTypeListener
{
    private const string PATH_PREFIX = '/api/portal/v1/';

    #[AsEventListener(event: KernelEvents::RESPONSE)]
    public function __invoke(ResponseEvent $event): void
    {
        $response = $event->getResponse();

        $isProblem = 422 === $response->getStatusCode()
            && str_starts_with($event->getRequest()->getPathInfo(), self::PATH_PREFIX)
            && str_starts_with((string) $response->headers->get('Content-Type'), 'application/problem+json');

        if (!$isProblem) {
            return;
        }

        $problem = json_decode((string) $response->getContent(), true);

        if (!\is_array($problem) || !\is_string($problem['type'] ?? null) || !str_starts_with($problem['type'], '/validation_errors/')) {
            return;
        }

        $problem['type']  = PortalProblemMap::VALIDATION_FAILED;
        $problem['title'] = PortalProblemMap::problem(PortalProblemMap::VALIDATION_FAILED)->getTitle();
        $response->setContent((string) json_encode($problem, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PRESERVE_ZERO_FRACTION));
    }
}
