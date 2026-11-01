<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Exception;

/** The owner account has no Portal profile yet (no invitation accepted): a 404. */
final class OwnerProfileNotFoundException extends \DomainException
{
    public function __construct(public readonly string $userId)
    {
        parent::__construct(\sprintf('Owner account "%s" has no Portal profile.', $userId));
    }
}
