<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Query\Console;

use App\Context\Clinic\Application\Port\ClinicConsoleReadRepositoryInterface;
use App\Context\Clinic\Application\Query\Console\ListConsoleMembers;
use App\Context\Clinic\Application\Query\Console\ListConsoleMembersHandler;
use App\Tests\Unit\Context\Clinic\Support\ConsoleFakes;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ListConsoleMembersHandlerTest extends TestCase
{
    private const string CLINIC = '01960000-0000-7000-8000-00000000cba1';
    private const string USER   = '01960000-0000-7000-8000-00000000cbb1';

    public function testTheMembersAreFilteredByClinicAndAccount(): void
    {
        $row     = ConsoleFakes::memberRow(self::USER, self::CLINIC, 'Claire', 'Martin');
        $console = $this->createMock(ClinicConsoleReadRepositoryInterface::class);
        $console->expects(self::exactly(2))->method('members')->willReturnCallback(static fn (?string $clinicId, ?string $userId): array => match ([$clinicId, $userId]) {
            [self::CLINIC, self::USER] => [$row],
            default => [],
        });

        self::assertSame([$row], new ListConsoleMembersHandler($console)(new ListConsoleMembers(self::CLINIC, self::USER)));
        self::assertSame([], new ListConsoleMembersHandler($console)(new ListConsoleMembers()));
    }

    #[DataProvider('provideAMemberIsPrintedWithTheirTitleCases')]
    public function testAMemberIsPrintedWithTheirTitle(string $firstName, string $lastName, ?string $title, string $printed): void
    {
        self::assertSame($printed, ConsoleFakes::memberRow(self::USER, self::CLINIC, $firstName, $lastName, 'Claire M.', professionalTitle: $title)->printedName());
    }

    /**
     * @return iterable<string, array{string, string, ?string, string}>
     */
    public static function provideAMemberIsPrintedWithTheirTitleCases(): iterable
    {
        yield 'doctor' => ['Claire', 'Martin', 'DR', 'Dr Claire Martin'];

        yield 'professor' => ['Claire', 'Martin', 'PR', 'Pr Claire Martin'];

        yield 'no title' => ['Inès', 'Benali', null, 'Inès Benali'];

        yield 'first name only' => ['Inès', '', null, 'Inès'];

        yield 'no name, display name' => ['', '', 'DR', 'Dr Claire M.'];
    }

    public function testOnlyAnActiveMembershipIsNotDisabled(): void
    {
        self::assertSame([false, true], [
            ConsoleFakes::memberRow(self::USER, self::CLINIC)->isDisabled(),
            ConsoleFakes::memberRow(self::USER, self::CLINIC, status: 'DISABLED')->isDisabled(),
        ]);
    }
}
