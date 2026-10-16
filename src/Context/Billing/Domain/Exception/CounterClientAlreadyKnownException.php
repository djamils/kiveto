<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\Exception;

/**
 * The newcomer the desk is about to create already has a record: their
 * phone number belongs to a client of the clinic.
 */
final class CounterClientAlreadyKnownException extends \DomainException
{
    public function __construct(public readonly string $clientId)
    {
        parent::__construct(\sprintf('The phone number already belongs to client %s.', $clientId));
    }
}
