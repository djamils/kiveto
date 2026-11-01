<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Application\Query\GetPortalAccount;

use App\Shared\Domain\ValueObject\PhoneNumber;
use App\System\IdentityAccess\Application\Query\GetPortalAccount\GetPortalAccount;
use App\System\IdentityAccess\Application\Query\GetPortalAccount\GetPortalAccountHandler;
use App\System\IdentityAccess\Domain\User;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use App\System\IdentityAccess\Domain\ValueObject\UserStatus;
use App\System\IdentityAccess\Domain\ValueObject\UserType;
use App\Tests\Unit\System\IdentityAccess\Support\InMemoryUsers;
use PHPUnit\Framework\TestCase;

final class GetPortalAccountHandlerTest extends TestCase
{
    private const string OWNER    = '01960000-0000-7000-8000-0000000004e1';
    private const string DISABLED = '01960000-0000-7000-8000-0000000004e2';
    private const string STAFF    = '01960000-0000-7000-8000-0000000004e3';
    private const string NO_PHONE = '01960000-0000-7000-8000-0000000004e4';

    public function testOnlyAnActiveOwnerAccountWithAPhoneIsAnswered(): void
    {
        $at    = new \DateTimeImmutable('2026-10-26 07:14:00');
        $phone = PhoneNumber::fromString('+33612345678');
        $users = new InMemoryUsers();
        $users->save(User::registerPortal(UserId::fromString(self::OWNER), $phone, 'marie@example.com', $at));
        $users->save(User::reconstitute(UserId::fromString(self::DISABLED), null, 'hash', $at, UserStatus::DISABLED, null, UserType::PORTAL, $phone, $at));
        $users->save(User::reconstitute(UserId::fromString(self::STAFF), 'vet@example.com', 'hash', $at, UserStatus::ACTIVE, null, UserType::CLINIC, $phone, $at));
        $users->save(User::reconstitute(UserId::fromString(self::NO_PHONE), null, 'hash', $at, UserStatus::ACTIVE, null, UserType::PORTAL));
        $handler = new GetPortalAccountHandler($users);

        $account = $handler(new GetPortalAccount(self::OWNER));
        self::assertNotNull($account);
        self::assertSame([self::OWNER, 'marie@example.com', '+33612345678', false], [$account->userId, $account->email, $account->phone, $account->emailVerified]);

        $users->users[self::OWNER]->confirmEmail('marie@example.com', $at);
        self::assertTrue($handler(new GetPortalAccount(self::OWNER))?->emailVerified);

        foreach ([self::DISABLED, self::STAFF, self::NO_PHONE, '01960000-0000-7000-8000-0000000004ff', 'not-a-uuid'] as $id) {
            self::assertNull($handler(new GetPortalAccount($id)), $id);
        }
    }
}
