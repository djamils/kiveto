<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Infrastructure\RateLimit;

use App\System\IdentityAccess\Application\Port\UnknownNumberSmsBudgetInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Database-backed limiters shared by every container: one key per network,
 * one per device hint, one global key. A further limiter (one token an
 * hour) keeps the critical log of the global cap to once per window.
 */
final readonly class RateLimiterUnknownNumberSmsBudget implements UnknownNumberSmsBudgetInterface
{
    private const string GLOBAL_KEY = 'global';

    public function __construct(
        #[Autowire(service: 'limiter.portal_otp_unknown_network')]
        private RateLimiterFactoryInterface $perNetwork,
        #[Autowire(service: 'limiter.portal_otp_unknown_device')]
        private RateLimiterFactoryInterface $perDevice,
        #[Autowire(service: 'limiter.portal_otp_unknown_global')]
        private RateLimiterFactoryInterface $budget,
        #[Autowire(service: 'limiter.portal_otp_unknown_alert')]
        private RateLimiterFactoryInterface $alerts,
        #[Autowire(service: 'monolog.logger.security')]
        private LoggerInterface $logger,
    ) {
    }

    public function consumeNetwork(string $networkKey): bool
    {
        return $this->perNetwork->create($networkKey)->consume()->isAccepted();
    }

    public function consumeDevice(string $deviceId): bool
    {
        return $this->perDevice->create($deviceId)->consume()->isAccepted();
    }

    public function consumeGlobal(): bool
    {
        if ($this->budget->create(self::GLOBAL_KEY)->consume()->isAccepted()) {
            return true;
        }

        if ($this->alerts->create(self::GLOBAL_KEY)->consume()->isAccepted()) {
            $this->logger->critical('Portal: hourly SMS budget for unknown numbers exhausted, sign-in codes are no longer sent to them.');
        }

        return false;
    }
}
