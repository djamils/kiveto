<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Domain\Exception;

/**
 * A connection cannot be opened: no platform is configured, or the
 * organisation already has one.
 */
final class PlatformConnectionRefusedException extends \DomainException
{
    public const string NO_PLATFORM    = 'no_platform';
    public const string ALREADY_OPENED = 'already_opened';

    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
