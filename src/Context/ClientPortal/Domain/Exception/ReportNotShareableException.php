<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Exception;

/** The consultation report cannot be shared on the Portal (yet). */
final class ReportNotShareableException extends \DomainException
{
    public const string UNKNOWN          = 'unknown';
    public const string NOT_CLOSED       = 'not_closed';
    public const string NO_ANIMAL        = 'no_animal';
    public const string NO_PRIMARY_OWNER = 'no_primary_owner';

    public function __construct(public readonly string $reason)
    {
        parent::__construct(\sprintf('The consultation report cannot be shared on the Portal (%s).', $reason));
    }
}
