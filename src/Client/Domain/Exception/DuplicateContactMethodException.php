<?php

declare(strict_types=1);

namespace App\Client\Domain\Exception;

final class DuplicateContactMethodException extends \DomainException
{
    public function __construct(string $type, string $value)
    {
        parent::__construct(\sprintf('Duplicate contact method: %s (%s) already exists.', $type, $value));
    }
}
