<?php

declare(strict_types=1);

namespace App\Context\Client\Domain\Exception;

use App\Context\Client\Domain\ValueObject\ConsentKind;

/**
 * Billing and clinical documents are sent anyway: their consent only picks
 * the channel, so it cannot be left without one.
 */
final class ConsentChannelRequiredException extends \DomainException
{
    public function __construct(ConsentKind $kind)
    {
        parent::__construct(\sprintf('Consent "%s" picks a channel and cannot be withdrawn.', $kind->value));
    }
}
