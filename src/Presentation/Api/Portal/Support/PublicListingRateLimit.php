<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Support;

use App\Presentation\Api\Portal\Problem\PortalProblemMap;
use App\Presentation\Api\Shared\Problem\ApiProblem;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * The quota of the public directory routes (`/clinic-listings…`, GET): 120
 * requests a minute per client IP (behind the trusted proxies), consumed
 * first by every listing provider; beyond it `429 rate_limited` with
 * `Retry-After`.
 */
final readonly class PublicListingRateLimit
{
    public function __construct(
        private RequestStack $requests,
        #[Autowire(service: 'limiter.portal_public_listing')]
        private RateLimiterFactoryInterface $perIp,
    ) {
    }

    /**
     * @throws ApiProblem 429
     */
    public function consume(): void
    {
        $limit = $this->perIp->create($this->requests->getMainRequest()?->getClientIp() ?? 'unknown')->consume();

        if (!$limit->isAccepted()) {
            $wait = max(1, $limit->getRetryAfter()->getTimestamp() - time());

            throw PortalProblemMap::problem(PortalProblemMap::RATE_LIMITED, ['Retry-After' => (string) $wait]);
        }
    }
}
