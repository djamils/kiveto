<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence;

/**
 * Type-safe accessors for DBAL associative rows.
 *
 * DBAL hands back `array<string, mixed>`, which makes feeding query results
 * into typed DTOs unpleasant under PHPStan's max level. These helpers narrow
 * the value before returning it so the calling code stays cast-free.
 */
final class DbalRow
{
    /**
     * @param array<string, mixed> $row
     */
    public static function string(array $row, string $key): string
    {
        $value = $row[$key] ?? throw new \LogicException(\sprintf('Missing key "%s" in row.', $key));
        if (\is_string($value)) {
            return $value;
        }
        if (\is_int($value) || \is_float($value) || \is_bool($value)) {
            return (string) $value;
        }
        throw new \LogicException(\sprintf('Row key "%s" is not stringable.', $key));
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function nullableString(array $row, string $key): ?string
    {
        $value = $row[$key] ?? null;
        if (null === $value) {
            return null;
        }
        if (\is_string($value)) {
            return $value;
        }
        if (\is_int($value) || \is_float($value) || \is_bool($value)) {
            return (string) $value;
        }
        throw new \LogicException(\sprintf('Row key "%s" is not stringable.', $key));
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function int(array $row, string $key): int
    {
        $value = $row[$key] ?? throw new \LogicException(\sprintf('Missing key "%s" in row.', $key));
        if (\is_int($value)) {
            return $value;
        }
        if (\is_string($value) && is_numeric($value)) {
            return (int) $value;
        }
        if (\is_float($value) || \is_bool($value)) {
            return (int) $value;
        }
        throw new \LogicException(\sprintf('Row key "%s" is not int-castable.', $key));
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function nullableInt(array $row, string $key): ?int
    {
        $value = $row[$key] ?? null;
        if (null === $value) {
            return null;
        }
        if (\is_int($value)) {
            return $value;
        }
        if (\is_string($value) && is_numeric($value)) {
            return (int) $value;
        }
        if (\is_float($value) || \is_bool($value)) {
            return (int) $value;
        }
        throw new \LogicException(\sprintf('Row key "%s" is not int-castable.', $key));
    }

    /**
     * Decode a binary UUID column to its RFC 4122 string form.
     *
     * @param array<string, mixed> $row
     */
    public static function uuid(array $row, string $key): string
    {
        $value = $row[$key] ?? throw new \LogicException(\sprintf('Missing key "%s" in row.', $key));
        if (!\is_string($value)) {
            throw new \LogicException(\sprintf('Row key "%s" is not a binary string.', $key));
        }

        return \Symfony\Component\Uid\Uuid::fromBinary($value)->toRfc4122();
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function nullableUuid(array $row, string $key): ?string
    {
        $value = $row[$key] ?? null;
        if (null === $value) {
            return null;
        }
        if (!\is_string($value)) {
            throw new \LogicException(\sprintf('Row key "%s" is not a binary string.', $key));
        }

        return \Symfony\Component\Uid\Uuid::fromBinary($value)->toRfc4122();
    }
}
