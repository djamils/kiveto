<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Exception;

/**
 * The appointment's status forbids the requested change (a pending request
 * marked as no-show, a confirmation of a declined request…). The message is
 * shown to the user as is.
 */
final class AppointmentNotModifiableException extends \DomainException
{
    public function __construct(string $message = 'Ce rendez-vous ne peut plus être modifié.')
    {
        parent::__construct($message);
    }
}
