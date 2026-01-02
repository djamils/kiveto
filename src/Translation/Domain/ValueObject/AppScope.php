<?php

declare(strict_types=1);

namespace App\Translation\Domain\ValueObject;

enum AppScope: string
{
    case CLINIC     = 'clinic';
    case PORTAL     = 'portal';
    case BACKOFFICE = 'backoffice';
    case SHARED     = 'shared';

    public static function fromString(string $value): self
    {
        $normalized = mb_strtolower(trim($value));

        return match ($normalized) {
            self::CLINIC->value     => self::CLINIC,
            self::PORTAL->value     => self::PORTAL,
            self::BACKOFFICE->value => self::BACKOFFICE,
            self::SHARED->value     => self::SHARED,
            default                 => throw new \InvalidArgumentException(\sprintf('Invalid scope "%s".', $value)),
        };
    }
}
