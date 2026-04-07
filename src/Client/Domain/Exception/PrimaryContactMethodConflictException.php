<?php

declare(strict_types=1);

namespace App\Client\Domain\Exception;

final class PrimaryContactMethodConflictException extends \DomainException
{
    public static function forPhones(): self
    {
        return new self('Only one primary phone contact method is allowed.');
    }

    public static function forEmails(): self
    {
        return new self('Only one primary email contact method is allowed.');
    }
}
