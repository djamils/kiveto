<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Application\Query\GetAccountSummaries;

use App\System\IdentityAccess\Application\Port\UserReadRepositoryInterface;
use App\System\IdentityAccess\Application\Query\GetAccountSummaries\AccountSummary;
use App\System\IdentityAccess\Application\Query\GetAccountSummaries\GetAccountSummaries;
use App\System\IdentityAccess\Application\Query\GetAccountSummaries\GetAccountSummariesHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GetAccountSummariesHandlerTest extends TestCase
{
    private const string FIRST  = '01960000-0000-7000-8000-00000000d1a1';
    private const string SECOND = '01960000-0000-7000-8000-00000000d1a2';

    public function testEachAccountIsAskedOnce(): void
    {
        $summary = self::summary('ACTIVE');
        $users   = $this->createMock(UserReadRepositoryInterface::class);
        $users->expects(self::once())->method('summaries')->with([self::FIRST, self::SECOND])->willReturn([self::FIRST => $summary]);

        self::assertSame([self::FIRST => $summary], new GetAccountSummariesHandler($users)(new GetAccountSummaries([self::FIRST, self::SECOND, self::FIRST])));
    }

    #[DataProvider('provideTheStatusOfAnAccountIsReadCases')]
    public function testTheStatusOfAnAccountIsRead(string $status, bool $active, bool $pending, bool $disabled): void
    {
        $summary = self::summary($status);

        self::assertSame([$active, $pending, $disabled], [$summary->isActive(), $summary->isPending(), $summary->isDisabled()]);
    }

    /**
     * @return iterable<string, array{string, bool, bool, bool}>
     */
    public static function provideTheStatusOfAnAccountIsReadCases(): iterable
    {
        yield 'active' => ['ACTIVE', true, false, false];

        yield 'pending' => ['PENDING', false, true, false];

        yield 'disabled' => ['DISABLED', false, false, true];
    }

    private static function summary(string $status): AccountSummary
    {
        return new AccountSummary(self::FIRST, 'CLINIC', 'claire@clinique.fr', null, $status, new \DateTimeImmutable('2026-09-01'), null);
    }
}
