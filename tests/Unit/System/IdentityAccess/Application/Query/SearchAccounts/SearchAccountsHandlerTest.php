<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Application\Query\SearchAccounts;

use App\System\IdentityAccess\Application\Port\UserReadRepositoryInterface;
use App\System\IdentityAccess\Application\Query\GetAccountSummaries\AccountSummary;
use App\System\IdentityAccess\Application\Query\SearchAccounts\AccountPage;
use App\System\IdentityAccess\Application\Query\SearchAccounts\SearchAccounts;
use App\System\IdentityAccess\Application\Query\SearchAccounts\SearchAccountsHandler;
use App\System\IdentityAccess\Domain\ValueObject\UserType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SearchAccountsHandlerTest extends TestCase
{
    public function testAPageOfTheAccountsOfOneTypeIsRead(): void
    {
        $summary = new AccountSummary('01960000-0000-7000-8000-00000000d2a1', 'PORTAL', null, '+33612345678', 'ACTIVE', new \DateTimeImmutable('2026-09-01'), null);
        $users   = $this->createMock(UserReadRepositoryInterface::class);
        $users->expects(self::once())->method('countAccounts')->with(UserType::PORTAL, '0612')->willReturn(25);
        $users->expects(self::once())->method('accounts')->with(UserType::PORTAL, '0612', 10, 10)->willReturn([$summary]);

        $page = new SearchAccountsHandler($users)(new SearchAccounts('PORTAL', ' 0612 ', 2, 10));

        self::assertSame([[$summary], 25, 2, 10, 3], [$page->items, $page->total, $page->page, $page->perPage, $page->pageCount()]);
    }

    #[DataProvider('provideThePageIsKeptWithinTheResultsCases')]
    public function testThePageIsKeptWithinTheResults(int $asked, int $perPage, int $page, int $offset, int $limit): void
    {
        $users = $this->createMock(UserReadRepositoryInterface::class);
        $users->expects(self::once())->method('countAccounts')->with(UserType::CLINIC, null)->willReturn(25);
        $users->expects(self::once())->method('accounts')->with(UserType::CLINIC, null, $offset, $limit)->willReturn([]);

        self::assertSame($page, new SearchAccountsHandler($users)(new SearchAccounts('CLINIC', '  ', $asked, $perPage))->page);
    }

    /**
     * @return iterable<string, array{int, int, int, int, int}>
     */
    public static function provideThePageIsKeptWithinTheResultsCases(): iterable
    {
        yield 'past the last page' => [9, 10, 3, 20, 10];

        yield 'before the first page' => [0, 10, 1, 0, 10];

        yield 'at least one per page' => [2, 0, 2, 1, 1];
    }

    public function testNoAccountIsReadWhenNoneMatches(): void
    {
        $users = $this->createMock(UserReadRepositoryInterface::class);
        $users->expects(self::once())->method('countAccounts')->with(UserType::BACKOFFICE, null)->willReturn(0);
        $users->expects(self::never())->method('accounts');

        $page = new SearchAccountsHandler($users)(new SearchAccounts('BACKOFFICE', page: 4));

        self::assertSame([[], 0, 1, 1], [$page->items, $page->total, $page->page, $page->pageCount()]);
    }

    public function testAPageCountIsAtLeastOne(): void
    {
        self::assertSame([1, 1, 2], [
            new AccountPage([], 0, 1, 10)->pageCount(),
            new AccountPage([], 10, 1, 10)->pageCount(),
            new AccountPage([], 11, 1, 10)->pageCount(),
        ]);
    }
}
