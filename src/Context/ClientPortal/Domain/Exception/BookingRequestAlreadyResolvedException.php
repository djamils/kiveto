<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Exception;

/** The booking request is no longer pending: a colleague, the expiry or the agenda resolved it first. */
final class BookingRequestAlreadyResolvedException extends \DomainException
{
    public function __construct(public readonly string $requestId)
    {
        parent::__construct('Demande déjà traitée.');
    }
}
