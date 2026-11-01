<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Exception;

/**
 * The account already has a confirmed link to the listed clinic (D16): it
 * books through `/clinics/{c}/booking`, not as a new client.
 */
final class AlreadyLinkedException extends \DomainException
{
    public function __construct(public readonly string $clinicId)
    {
        parent::__construct(\sprintf('The owner account is already linked to clinic "%s".', $clinicId));
    }
}
