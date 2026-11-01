<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Infrastructure\RateLimit;

use App\System\IdentityAccess\Application\Port\PortalOtpQuotasInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * The limiters the Portal API consumes per phone. A reset deletes their
 * rows of `shared__rate_limiter_cache` (own connection, autocommit).
 */
final readonly class RateLimiterPortalOtpQuotas implements PortalOtpQuotasInterface
{
    public function __construct(
        #[Autowire(service: 'limiter.portal_otp_phone')]
        private RateLimiterFactoryInterface $perPhone,
        #[Autowire(service: 'limiter.portal_otp_phone_daily')]
        private RateLimiterFactoryInterface $perPhoneDaily,
    ) {
    }

    public function forgetPhone(string $phone): void
    {
        $this->perPhone->create($phone)->reset();
        $this->perPhoneDaily->create($phone)->reset();
    }
}
