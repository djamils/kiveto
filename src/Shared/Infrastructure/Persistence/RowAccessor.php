<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence;

/**
 * Type-safe accessors for associative rows returned by raw SQL queries.
 *
 * ## Why this class exists
 *
 * Doctrine DBAL methods like `Connection::fetchAssociative()` and
 * `fetchAllAssociative()` return data shaped as `array<string, mixed>` —
 * the driver has no idea which columns are strings, ints, UUIDs, etc.
 *
 * Our query handlers feed those rows into strictly-typed read DTOs. Under
 * PHPStan's max level, that creates two pain points:
 *
 *   1. **Plain casts on `mixed` are forbidden** — `(string) $row['id']`
 *      raises "Cannot cast mixed to string" because PHPStan does not trust
 *      that `mixed` is scalar.
 *   2. **Passing `mixed` directly to a typed parameter is forbidden** —
 *      `new MyDTO(id: $row['id'])` raises "expects string, mixed given".
 *
 * We could litter every query handler with manual `is_string()` ladders,
 * `assert()` calls, or PHPDoc `@var` hints — but that would be verbose,
 * inconsistent across files, and hide bugs when a column unexpectedly
 * comes back as `null`.
 *
 * `RowAccessor` centralises that narrowing logic in one place: each helper
 * method takes the raw row + a column name, runs an `is_*` check on the
 * value, and returns a properly typed scalar. Calling code stays cast-free
 * and reads naturally:
 *
 *     $dto = new ConsultationDTO(
 *         id:           RowAccessor::uuid($row, 'id'),
 *         clinicId:     RowAccessor::uuid($row, 'clinic_id'),
 *         status:       RowAccessor::string($row, 'status'),
 *         priority:     RowAccessor::int($row, 'priority'),
 *         closedAtUtc:  RowAccessor::nullableString($row, 'closed_at_utc'),
 *     );
 *
 * If a row contains an unexpected type (e.g. an object where a scalar was
 * expected), the helpers throw `LogicException` with the column name —
 * loud and easy to debug.
 *
 * ## When to use it
 *
 * Use `RowAccessor` in any class that hydrates a DTO/value object from a
 * raw DBAL row (typically Application query handlers and Infrastructure
 * read-side adapters). Do **not** use it for ORM entities — Doctrine ORM
 * already returns properly-typed objects via metadata mapping.
 */
final class RowAccessor
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
