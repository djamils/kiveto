<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Application\Pagination;

use App\Shared\Application\Pagination\Cursor;
use App\Shared\Application\Pagination\CursorPage;
use App\Shared\Application\Pagination\InvalidCursorException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CursorTest extends TestCase
{
    public function testEncodeDecodeRoundTripKeepsThePosition(): void
    {
        $position = ['createdAt' => '2026-10-29T10:00:00+00:00', 'id' => '01960000-0000-7000-8000-000000000001', 'rank' => 3];

        $encoded = Cursor::fromPosition($position)->encode();

        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $encoded);
        self::assertSame($position, Cursor::decode($encoded)->position());
    }

    public function testAnEmptyPositionIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Cursor::fromPosition([]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideATamperedCursorIsRejectedCases(): iterable
    {
        $encode = static fn (string $json): string => rtrim(strtr(base64_encode($json), '+/', '-_'), '=');

        yield 'empty' => [''];
        yield 'not base64url' => ['abc+/=='];
        yield 'not json' => [$encode('not json')];
        yield 'too deep' => [$encode('{"v":1,"p":{"a":{"b":{"c":1}}}}')];
        yield 'not an object' => [$encode('"text"')];
        yield 'wrong version' => [$encode('{"v":2,"p":{"id":"x"}}')];
        yield 'missing position' => [$encode('{"v":1}')];
        yield 'position not an object' => [$encode('{"v":1,"p":"x"}')];
        yield 'empty position' => [$encode('{"v":1,"p":{}}')];
        yield 'list position' => [$encode('{"v":1,"p":["x"]}')];
        yield 'non scalar value' => [$encode('{"v":1,"p":{"id":[1]}}')];
        yield 'float value' => [$encode('{"v":1,"p":{"id":1.5}}')];
        yield 'truncated' => [substr(Cursor::fromPosition(['id' => 'abcdef'])->encode(), 0, -3)];
    }

    #[DataProvider('provideATamperedCursorIsRejectedCases')]
    public function testATamperedCursorIsRejected(string $encoded): void
    {
        $this->expectException(InvalidCursorException::class);
        $this->expectExceptionMessage('The pagination cursor is invalid.');

        Cursor::decode($encoded);
    }

    /**
     * @return iterable<string, array{?int, int}>
     */
    public static function provideTheLimitIsClampedCases(): iterable
    {
        yield 'default' => [null, 20];
        yield 'within bounds' => [35, 35];
        yield 'maximum' => [50, 50];
        yield 'above maximum' => [51, 50];
        yield 'far above maximum' => [10_000, 50];
        yield 'minimum' => [1, 1];
        yield 'zero' => [0, 1];
        yield 'negative' => [-5, 1];
    }

    #[DataProvider('provideTheLimitIsClampedCases')]
    public function testTheLimitIsClamped(?int $requested, int $expected): void
    {
        self::assertSame($expected, Cursor::clampLimit($requested));
    }

    public function testAPageCarriesItsItemsAndTheNextCursor(): void
    {
        $page = new CursorPage(['a', 'b'], 'next');

        self::assertSame(['a', 'b'], $page->items);
        self::assertSame('next', $page->nextCursor);
        self::assertNull(new CursorPage([], null)->nextCursor);
    }
}
