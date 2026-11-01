<?php

declare(strict_types=1);

namespace App\Context\ClinicListing\Application\Query\SearchClinicListings;

use App\Context\ClinicListing\Domain\Exception\InvalidCursorException;
use Symfony\Component\Uid\Uuid;

/**
 * The keyset position a page ends on, opaque to the client (base64url of a
 * JSON object): `{d, id, lat, lng}` for a search around a point (distance in
 * whole metres, and the point it was measured from), `{name, id}` otherwise.
 */
final readonly class ListingCursor
{
    private function __construct(
        public string $id,
        public ?int $distanceM,
        public ?string $name,
    ) {
    }

    public static function encodeDistance(int $distanceM, string $id, float $lat, float $lng): string
    {
        return self::encode(['d' => $distanceM, 'id' => $id, 'lat' => $lat, 'lng' => $lng]);
    }

    public static function encodeName(string $name, string $id): string
    {
        return self::encode(['name' => $name, 'id' => $id]);
    }

    /**
     * Reads a cursor for a search around (lat, lng), or by name when both are null.
     *
     * @throws InvalidCursorException unreadable, or issued for another ordering or point
     */
    public static function decode(string $cursor, ?float $lat, ?float $lng): self
    {
        $json = base64_decode(strtr($cursor, '-_', '+/'), true);
        $data = false === $json ? null : json_decode($json, true);
        $id   = \is_array($data) ? ($data['id'] ?? null) : null;

        if (!\is_array($data) || !\is_string($id) || !Uuid::isValid($id)) {
            throw new InvalidCursorException();
        }

        if (null === $lat || null === $lng) {
            $name = $data['name'] ?? null;

            if (!\is_string($name) || \array_key_exists('d', $data)) {
                throw new InvalidCursorException();
            }

            return new self($id, null, $name);
        }

        $distance = $data['d'] ?? null;
        $from     = [$data['lat'] ?? null, $data['lng'] ?? null];

        if (!\is_int($distance) || !is_numeric($from[0]) || !is_numeric($from[1]) || (float) $from[0] !== $lat || (float) $from[1] !== $lng) {
            throw new InvalidCursorException();
        }

        return new self($id, $distance, null);
    }

    /**
     * @param array<string, string|int|float> $position
     */
    private static function encode(array $position): string
    {
        return rtrim(strtr(base64_encode(json_encode($position, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION)), '+/', '-_'), '=');
    }
}
