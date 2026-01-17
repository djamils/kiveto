<?php

declare(strict_types=1);

namespace App\Client\Domain\Exception;

final class DuplicateContactMethodException extends \DomainException
{
    public static function create(string $type, string $value): self
    {
        return new self(\sprintf('Duplicate contact method: %s (%s) already exists.', $type, $value));
    }
}
