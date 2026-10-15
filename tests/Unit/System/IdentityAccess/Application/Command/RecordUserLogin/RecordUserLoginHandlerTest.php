<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Application\Command\RecordUserLogin;

use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Application\Command\RecordUserLogin\RecordUserLogin;
use App\System\IdentityAccess\Application\Command\RecordUserLogin\RecordUserLoginHandler;
use App\System\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

final class RecordUserLoginHandlerTest extends TestCase
{
    public function testStampsTheSignInAtTheClocksTime(): void
    {
        $now = new \DateTimeImmutable('2026-10-15T08:42:00Z');

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn($now);

        $users = $this->createMock(UserRepositoryInterface::class);
        $users->expects(self::once())->method('recordLogin')->with(
            self::callback(static fn (UserId $id): bool => '11111111-1111-1111-1111-111111111111' === $id->toString()),
            $now,
        );

        (new RecordUserLoginHandler($users, $clock))(new RecordUserLogin('11111111-1111-1111-1111-111111111111'));
    }
}
