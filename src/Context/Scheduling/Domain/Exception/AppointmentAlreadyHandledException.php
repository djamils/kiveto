<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Exception;

/**
 * A confirm or decline of a request that is no longer pending: someone (or
 * the expiry) handled it first. The message is shown to the user as is.
 */
final class AppointmentAlreadyHandledException extends \DomainException
{
    public function __construct()
    {
        parent::__construct('Ce RDV a déjà été traité.');
    }
}
