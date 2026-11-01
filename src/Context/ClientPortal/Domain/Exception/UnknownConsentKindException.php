<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Exception;

/** A consent kind the Portal does not know (a 404 on its route). */
final class UnknownConsentKindException extends \DomainException
{
    public function __construct(public readonly string $kind)
    {
        parent::__construct(\sprintf('Unknown consent kind "%s".', $kind));
    }
}
