<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http\Api;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Pre-firewall IP rate limit on /api/clinic/* — guards against
 * unauthenticated brute-force and scraping. Runs at priority 16
 * (after router resolution at 32, before firewall at 8).
 */
final readonly class IpRateLimitListener implements EventSubscriberInterface
{
    public function __construct(
        private RateLimiterFactoryInterface $apiClinicIpLimiter,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 16],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (1 !== preg_match('#^/api/clinic(/|$)#', $request->getPathInfo())) {
            return;
        }

        $ip    = $request->getClientIp() ?? 'unknown';
        $limit = $this->apiClinicIpLimiter->create($ip)->consume(1);

        if ($limit->isAccepted()) {
            return;
        }

        $retryAfter = max(1, $limit->getRetryAfter()->getTimestamp() - time());

        $event->setResponse(new JsonResponse(
            ['error' => 'too_many_requests'],
            Response::HTTP_TOO_MANY_REQUESTS,
            ['Retry-After' => (string) $retryAfter],
        ));
    }
}
