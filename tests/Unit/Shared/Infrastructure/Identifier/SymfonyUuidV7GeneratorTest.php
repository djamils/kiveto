<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Identifier;

use App\Shared\Infrastructure\Identifier\SymfonyUuidV7Generator;
use PHPUnit\Framework\TestCase;

final class SymfonyUuidV7GeneratorTest extends TestCase
{
    public function testGeneratesValidUuid(): void
    {
        $generator = new SymfonyUuidV7Generator();

        $uuid = $generator->generate();

        self::assertIsString($uuid);
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $uuid,
            'Generated UUID should be a valid UUIDv7'
        );
    }

    public function testGeneratesDifferentUuids(): void
    {
        $generator = new SymfonyUuidV7Generator();

        $uuid1 = $generator->generate();
        $uuid2 = $generator->generate();

        self::assertNotSame($uuid1, $uuid2);
    }

    public function testGeneratesUuidFromDateTime(): void
    {
        $generator = new SymfonyUuidV7Generator();
        $dateTime  = new \DateTimeImmutable('2025-01-01 12:00:00');

        $uuid = $generator->generateFromDateTime($dateTime);

        self::assertIsString($uuid);
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $uuid,
            'Generated UUID should be a valid UUIDv7'
        );
    }

    public function testSameDatetimeGeneratesSameUuid(): void
    {
        $generator = new SymfonyUuidV7Generator();
        $dateTime  = new \DateTimeImmutable('2025-01-01 12:00:00');

        $uuid1 = $generator->generateFromDateTime($dateTime);
        $uuid2 = $generator->generateFromDateTime($dateTime);

        self::assertSame($uuid1, $uuid2, 'Same datetime should generate same UUIDv7');
    }
}
