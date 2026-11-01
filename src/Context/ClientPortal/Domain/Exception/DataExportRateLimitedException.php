<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Exception;

/** One data export per account per 24 hours: a 429. */
final class DataExportRateLimitedException extends \DomainException
{
    public function __construct(public readonly int $retryAfterSeconds)
    {
        parent::__construct(\sprintf('A data export was already requested: retry in %d seconds.', $retryAfterSeconds));
    }
}
