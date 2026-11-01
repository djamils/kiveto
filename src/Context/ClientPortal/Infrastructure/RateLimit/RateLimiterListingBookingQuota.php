<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\RateLimit;

use App\Context\ClientPortal\Application\Port\ListingBookingQuotaInterface;
use App\Context\ClientPortal\Domain\Exception\ListingBookingRateLimitedException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * `limiter.portal_listing_booking`: 10 tokens per account per 24 hours,
 * kept in the database on the rate limiter's own connection.
 */
final readonly class RateLimiterListingBookingQuota implements ListingBookingQuotaInterface
{
    public function __construct(
        #[Autowire(service: 'limiter.portal_listing_booking')]
        private RateLimiterFactoryInterface $limiter,
    ) {
    }

    public function consume(string $userId): void
    {
        $limit = $this->limiter->create($userId)->consume();

        if (!$limit->isAccepted()) {
            throw new ListingBookingRateLimitedException(max(1, $limit->getRetryAfter()->getTimestamp() - time()));
        }
    }
}
