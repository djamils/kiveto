<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Exception;

/**
 * An answer or a request field the rules refuse (S6e): a 422
 * `validation_failed` pointing at `propertyPath`.
 */
final class InvalidPortalRequestException extends \DomainException
{
    public function __construct(public readonly string $propertyPath, string $message)
    {
        parent::__construct($message);
    }
}
