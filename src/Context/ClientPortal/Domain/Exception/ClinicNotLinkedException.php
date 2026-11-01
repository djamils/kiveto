<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Exception;

/**
 * The owner account has no confirmed link to a client file of that clinic:
 * it has nobody to book for there (a 404).
 */
final class ClinicNotLinkedException extends \DomainException
{
    public function __construct(public readonly string $clinicId)
    {
        parent::__construct(\sprintf('The owner account is not linked to clinic "%s".', $clinicId));
    }
}
