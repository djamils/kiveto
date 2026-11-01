<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\RateLimit;

use App\Context\ClientPortal\Application\Port\PortalRequestQuotaInterface;
use App\Context\ClientPortal\Domain\Exception\PortalRequestRateLimitedException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * `limiter.portal_requests`: 10 tokens per account and clinic per 24 hours,
 * kept in the database on the rate limiter's own connection.
 */
final readonly class RateLimiterPortalRequestQuota implements PortalRequestQuotaInterface
{
    public function __construct(
        #[Autowire(service: 'limiter.portal_requests')]
        private RateLimiterFactoryInterface $limiter,
    ) {
    }

    public function consume(string $userId, string $clinicId): void
    {
        $limit = $this->limiter->create($userId . ':' . $clinicId)->consume();

        if (!$limit->isAccepted()) {
            throw new PortalRequestRateLimitedException(max(1, $limit->getRetryAfter()->getTimestamp() - time()));
        }
    }
}
