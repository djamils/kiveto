<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation\Backoffice\ViewModel;

use App\Presentation\Backoffice\ViewModel\ConsoleFormat;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConsoleFormatTest extends TestCase
{
    private \DateTimeZone $paris;

    protected function setUp(): void
    {
        $this->paris = new \DateTimeZone('Europe/Paris');
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function provideWhenSaysHowLongAgoCases(): iterable
    {
        // now = 2026-11-06 10:00 UTC = 11:00 in Paris
        yield 'seconds ago' => ['2026-11-06T09:59:30Z', 'à l’instant'];
        yield 'minutes ago' => ['2026-11-06T09:57:00Z', 'il y a 3 min'];
        yield 'hours ago' => ['2026-11-06T08:00:00Z', 'il y a 2 h'];
        yield 'earlier today' => ['2026-11-06T02:12:00Z', 'aujourd’hui, 03:12'];
        yield 'yesterday' => ['2026-11-05T17:40:00Z', 'hier, 18:40'];
        yield 'this year' => ['2026-10-28T09:00:00Z', '28 oct.'];
        yield 'another year' => ['2025-10-12T09:00:00Z', '12 oct. 2025'];
        yield 'in the future' => ['2026-11-08T09:00:00Z', '8 nov.'];
    }

    #[DataProvider('provideWhenSaysHowLongAgoCases')]
    public function testWhenSaysHowLongAgo(string $at, string $expected): void
    {
        self::assertSame($expected, ConsoleFormat::when(new \DateTimeImmutable($at), new \DateTimeImmutable('2026-11-06T10:00:00Z'), $this->paris));
    }

    public function testTheDatesAreInFrench(): void
    {
        $at = new \DateTimeImmutable('2026-10-27T06:12:00Z');

        self::assertSame('27 oct.', ConsoleFormat::shortDate($at, $this->paris));
        self::assertSame('27 oct. 2026', ConsoleFormat::shortDate($at, $this->paris, true));
        self::assertSame('27 octobre 2026', ConsoleFormat::longDate($at, $this->paris));
        self::assertSame('mardi 27 octobre 2026', ConsoleFormat::dayDate($at, $this->paris));
        self::assertSame('mardi 27 octobre 2026, 07:12', ConsoleFormat::dayDate($at, $this->paris, true));
        self::assertSame('mar. 27 oct., 07:12', ConsoleFormat::shortDayTime($at, $this->paris));
        self::assertSame('27/10 07:12', ConsoleFormat::logDate($at, $this->paris));
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function provideInitialsCases(): iterable
    {
        yield 'doctor' => ['Dr Claire Martin', 'CM'];
        yield 'two words' => ['Djamil Soidiki', 'DS'];
        yield 'three words' => ['Marie-Anne de La Tour', 'MT'];
        yield 'an e-mail' => ['admin@kiveto.local', 'AD'];
        yield 'dotted mail' => ['c.martin@clinique.fr', 'CM'];
        yield 'one word' => ['Kiveto', 'KI'];
        yield 'nothing' => ['  ', '?'];
    }

    #[DataProvider('provideInitialsCases')]
    public function testInitials(string $name, string $expected): void
    {
        self::assertSame($expected, ConsoleFormat::initials($name));
    }

    public function testAddressesAreShownInPart(): void
    {
        self::assertSame('91.x.x.12', ConsoleFormat::maskedIp('91.12.34.12'));
        self::assertSame('2001:db8:…', ConsoleFormat::maskedIp('2001:db8::1'));
        self::assertSame('—', ConsoleFormat::maskedIp(null));
        self::assertSame('—', ConsoleFormat::maskedIp(''));
    }

    public function testDurationsAndAmounts(): void
    {
        self::assertSame('18 min', ConsoleFormat::duration(18 * 60 + 10));
        self::assertSame('0 min', ConsoleFormat::duration(-5));
        self::assertSame('1 h 05', ConsoleFormat::duration(65 * 60));
        self::assertSame('396 €', ConsoleFormat::euros(39_600));
        self::assertSame('1 188 €', ConsoleFormat::euros(118_800));
        self::assertSame('99,50 €', ConsoleFormat::euros(9_950));
    }
}
