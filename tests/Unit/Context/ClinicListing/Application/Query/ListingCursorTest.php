<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClinicListing\Application\Query;

use App\Context\ClinicListing\Application\Query\SearchClinicListings\ListingCursor;
use App\Context\ClinicListing\Domain\Exception\InvalidCursorException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ListingCursorTest extends TestCase
{
    private const string ID = '01960000-0000-7000-8000-0000000a4001';

    public function testBothKindsRoundTrip(): void
    {
        $byName = ListingCursor::encodeName('Clinique Ébène/+', self::ID);
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $byName);
        $read = ListingCursor::decode($byName, null, null);
        self::assertSame([self::ID, 'Clinique Ébène/+', null], [$read->id, $read->name, $read->distanceM]);

        // A whole-degree point survives the round trip as a float
        $byDistance = ListingCursor::encodeDistance(1_250, self::ID, 48.0, -2.5);
        $read       = ListingCursor::decode($byDistance, 48.0, -2.5);
        self::assertSame([self::ID, null, 1_250], [$read->id, $read->name, $read->distanceM]);
    }

    /**
     * @return iterable<string, array{string, ?float, ?float}>
     */
    public static function provideAnUnreadableOrMismatchedCursorIsRefusedCases(): iterable
    {
        $encode = static fn (mixed $data): string => rtrim(strtr(base64_encode((string) json_encode($data)), '+/', '-_'), '=');

        yield 'not base64' => ['%%%', null, null];
        yield 'not json' => [$encode(null) . 'x', null, null];
        yield 'not an object' => [$encode('text'), null, null];
        yield 'no id' => [$encode(['name' => 'A']), null, null];
        yield 'malformed id' => [$encode(['name' => 'A', 'id' => 'nope']), null, null];
        yield 'distance cursor without point' => [ListingCursor::encodeDistance(10, self::ID, 48.85, 2.35), null, null];
        yield 'name cursor with distance' => [$encode(['name' => 'A', 'id' => self::ID, 'd' => 3]), null, null];
        yield 'name cursor without name' => [$encode(['id' => self::ID]), null, null];
        yield 'name cursor around a point' => [ListingCursor::encodeName('A', self::ID), 48.85, 2.35];
        yield 'distance not whole' => [$encode(['d' => 1.5, 'id' => self::ID, 'lat' => 48.85, 'lng' => 2.35]), 48.85, 2.35];
        yield 'point missing' => [$encode(['d' => 1, 'id' => self::ID, 'lat' => 48.85]), 48.85, 2.35];
        yield 'other latitude' => [ListingCursor::encodeDistance(10, self::ID, 48.85, 2.35), 48.851, 2.35];
        yield 'other longitude' => [ListingCursor::encodeDistance(10, self::ID, 48.85, 2.35), 48.85, 2.351];
    }

    #[DataProvider('provideAnUnreadableOrMismatchedCursorIsRefusedCases')]
    public function testAnUnreadableOrMismatchedCursorIsRefused(string $cursor, ?float $lat, ?float $lng): void
    {
        $this->expectException(InvalidCursorException::class);

        ListingCursor::decode($cursor, $lat, $lng);
    }
}
