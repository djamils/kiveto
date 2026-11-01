<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Export;

use App\Context\ClientPortal\Application\Port\DataExportQuotaInterface;
use App\Context\ClientPortal\Domain\Exception\DataExportRateLimitedException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * `limiter.portal_data_export`: one token per account per 24 hours, kept in
 * the database on the rate limiter's own connection. A refund resets the
 * account's window.
 */
final readonly class RateLimiterDataExportQuota implements DataExportQuotaInterface
{
    public function __construct(
        #[Autowire(service: 'limiter.portal_data_export')]
        private RateLimiterFactoryInterface $limiter,
    ) {
    }

    public function consume(string $userId): void
    {
        $limit = $this->limiter->create($userId)->consume();

        if (!$limit->isAccepted()) {
            throw new DataExportRateLimitedException(max(1, $limit->getRetryAfter()->getTimestamp() - time()));
        }
    }

    public function refund(string $userId): void
    {
        $this->limiter->create($userId)->reset();
    }
}
