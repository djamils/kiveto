<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Query\Console;

use App\Context\Clinic\Application\Port\ClinicConsoleReadRepositoryInterface;
use App\Context\Clinic\Application\Query\Console\ListConsoleClinics;
use App\Context\Clinic\Application\Query\Console\ListConsoleClinicsHandler;
use App\Tests\Unit\Context\Clinic\Support\ConsoleFakes;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ListConsoleClinicsHandlerTest extends TestCase
{
    private const string CLINIC = '01960000-0000-7000-8000-00000000caa1';

    public function testOneClinicIsReadByItsId(): void
    {
        $row     = ConsoleFakes::clinicRow(self::CLINIC);
        $console = $this->createMock(ClinicConsoleReadRepositoryInterface::class);
        $console->expects(self::exactly(2))->method('clinic')->willReturnCallback(static fn (string $id): ?object => self::CLINIC === $id ? $row : null);
        $console->expects(self::never())->method('clinics');

        self::assertSame([$row], new ListConsoleClinicsHandler($console)(new ListConsoleClinics(search: 'ignored', clinicId: self::CLINIC)));
        self::assertSame([], new ListConsoleClinicsHandler($console)(new ListConsoleClinics(clinicId: '01960000-0000-7000-8000-00000000caff')));
    }

    public function testASearchNarrowsTheClinics(): void
    {
        $row     = ConsoleFakes::clinicRow(self::CLINIC);
        $console = $this->createMock(ClinicConsoleReadRepositoryInterface::class);
        $console->expects(self::once())->method('searchClinics')->with(' Lyon ', 20)->willReturn([$row]);
        $console->expects(self::never())->method('clinics');

        self::assertSame([$row], new ListConsoleClinicsHandler($console)(new ListConsoleClinics(' Lyon ', limit: 20)));
    }

    #[DataProvider('provideWithoutSearchEveryClinicIsListedCases')]
    public function testWithoutSearchEveryClinicIsListed(?string $search): void
    {
        $row     = ConsoleFakes::clinicRow(self::CLINIC);
        $console = $this->createMock(ClinicConsoleReadRepositoryInterface::class);
        $console->expects(self::once())->method('clinics')->willReturn([$row]);
        $console->expects(self::never())->method('searchClinics');

        self::assertSame([$row], new ListConsoleClinicsHandler($console)(new ListConsoleClinics($search)));
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function provideWithoutSearchEveryClinicIsListedCases(): iterable
    {
        yield 'no search' => [null];

        yield 'blank search' => ['   '];
    }

    public function testOnlyAnActiveClinicIsNotSuspended(): void
    {
        self::assertSame([false, true], [ConsoleFakes::clinicRow(self::CLINIC)->isSuspended(), ConsoleFakes::clinicRow(self::CLINIC, 'suspended')->isSuspended()]);
    }
}
