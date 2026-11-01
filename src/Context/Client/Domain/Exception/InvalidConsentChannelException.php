<?php

declare(strict_types=1);

namespace App\Context\Client\Domain\Exception;

/**
 * A consent only carries the automated channels, `sms` and `email`.
 */
final class InvalidConsentChannelException extends \InvalidArgumentException
{
    public function __construct(string $channel)
    {
        parent::__construct(\sprintf('Channel "%s" cannot carry a consent.', $channel));
    }
}
