<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Application\Query\GetUserStatuses;

use App\System\IdentityAccess\Application\Port\UserReadRepositoryInterface;
use App\System\IdentityAccess\Application\Query\GetUserStatuses\GetUserStatuses;
use App\System\IdentityAccess\Application\Query\GetUserStatuses\GetUserStatusesHandler;
use PHPUnit\Framework\TestCase;

final class GetUserStatusesHandlerTest extends TestCase
{
    public function testAsksTheStatusesOfEachDistinctUserOnce(): void
    {
        $users = $this->createMock(UserReadRepositoryInterface::class);
        $users->expects(self::once())->method('statuses')->with(['a', 'b'])->willReturn(['a' => 'ACTIVE', 'b' => 'PENDING']);

        self::assertSame(['a' => 'ACTIVE', 'b' => 'PENDING'], new GetUserStatusesHandler($users)(new GetUserStatuses(['a', 'b', 'a'])));
    }

    public function testAnEmptyListAsksNothing(): void
    {
        $users = $this->createMock(UserReadRepositoryInterface::class);
        $users->expects(self::never())->method('statuses');

        self::assertSame([], new GetUserStatusesHandler($users)(new GetUserStatuses([])));
    }
}
