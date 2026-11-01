<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Exception;

/**
 * A pending request can no longer be confirmed: its `pendingExpiresAt` has
 * passed (it is, or is about to be, declined as expired). The message is
 * shown to the user as is.
 */
final class PendingAppointmentExpiredException extends \DomainException
{
    public function __construct()
    {
        parent::__construct('Demande expirée.');
    }
}
