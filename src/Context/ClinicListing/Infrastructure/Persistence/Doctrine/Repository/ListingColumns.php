<?php

declare(strict_types=1);

namespace App\Context\ClinicListing\Infrastructure\Persistence\Doctrine\Repository;

use Symfony\Component\Uid\Uuid;

/**
 * Conversions shared by the ClinicListing SQL classes: UUIDs as BINARY(16),
 * dates as UTC DATETIME strings, and the JSON columns read defensively.
 */
final class ListingColumns
{
    public const string LISTINGS = 'clinic_listing__clinic_listings';
    public const string SETTINGS = 'clinic_listing__listing_settings';

    public static function binary(string $uuid): string
    {
        return Uuid::fromString($uuid)->toBinary();
    }

    public static function utc(\DateTimeImmutable $at): string
    {
        return $at->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    /**
     * A JSON list of strings; anything else reads as empty.
     *
     * @return list<string>
     */
    public static function tags(string $json): array
    {
        $values = json_decode($json, true);

        return \is_array($values) ? array_values(array_filter($values, \is_string(...))) : [];
    }

    /**
     * The weekly slots; malformed entries are skipped.
     *
     * @return list<array{day: int, opensAt: string, closesAt: string}>
     */
    public static function openingHours(string $json): array
    {
        $values = json_decode($json, true);
        $slots  = [];

        foreach (\is_array($values) ? $values : [] as $slot) {
            if (\is_array($slot) && \is_int($slot['day'] ?? null) && \is_string($slot['opensAt'] ?? null) && \is_string($slot['closesAt'] ?? null)) {
                $slots[] = ['day' => $slot['day'], 'opensAt' => $slot['opensAt'], 'closesAt' => $slot['closesAt']];
            }
        }

        return $slots;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function coordinate(array $row, string $key): ?float
    {
        $value = $row[$key] ?? null;

        return is_numeric($value) ? (float) $value : null;
    }
}
