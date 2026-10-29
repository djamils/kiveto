<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository;

use Symfony\Component\Uid\Uuid;

/**
 * Conversions shared by the ClientPortal SQL repositories: UUIDs as
 * BINARY(16), dates as UTC DATETIME strings.
 */
final class SqlValues
{
    public static function binary(string $uuid): string
    {
        return Uuid::fromString($uuid)->toBinary();
    }

    public static function nullableBinary(?string $uuid): ?string
    {
        return null === $uuid ? null : self::binary($uuid);
    }

    public static function utc(\DateTimeImmutable $at): string
    {
        return $at->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    public static function nullableUtc(?\DateTimeImmutable $at): ?string
    {
        return null === $at ? null : self::utc($at);
    }

    public static function date(string $value): \DateTimeImmutable
    {
        return new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
    }

    public static function nullableDate(?string $value): ?\DateTimeImmutable
    {
        return null === $value ? null : self::date($value);
    }
}
