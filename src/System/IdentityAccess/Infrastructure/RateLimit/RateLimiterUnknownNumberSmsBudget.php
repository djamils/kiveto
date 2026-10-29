<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Infrastructure\RateLimit;

use App\System\IdentityAccess\Application\Port\UnknownNumberSmsBudgetInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * One global key in the database-backed limiter, shared by every container.
 * A second limiter (one token an hour) keeps the alert to once per window.
 */
final readonly class RateLimiterUnknownNumberSmsBudget implements UnknownNumberSmsBudgetInterface
{
    private const string KEY = 'global';

    public function __construct(
        #[Autowire(service: 'limiter.portal_otp_unknown_global')]
        private RateLimiterFactoryInterface $budget,
        #[Autowire(service: 'limiter.portal_otp_unknown_alert')]
        private RateLimiterFactoryInterface $alerts,
        #[Autowire(service: 'monolog.logger.security')]
        private LoggerInterface $logger,
    ) {
    }

    public function consume(): bool
    {
        if ($this->budget->create(self::KEY)->consume()->isAccepted()) {
            return true;
        }

        if ($this->alerts->create(self::KEY)->consume()->isAccepted()) {
            $this->logger->alert('Portal: hourly SMS budget for unknown numbers exhausted, sign-in codes are no longer sent to them.');
        }

        return false;
    }
}
