<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Application\Command\ChangeOwnPassword;

use App\System\IdentityAccess\Application\Command\ChangeOwnPassword\ChangeOwnPassword;
use App\System\IdentityAccess\Application\Command\ChangeOwnPassword\ChangeOwnPasswordHandler;
use App\System\IdentityAccess\Application\Exception\UnacceptablePasswordException;
use App\System\IdentityAccess\Application\Port\Security\PasswordHashVerifierInterface;
use App\System\IdentityAccess\Application\Query\AuthenticateUser\Exception\InvalidCredentialsException;
use App\System\IdentityAccess\Domain\User;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use App\System\IdentityAccess\Domain\ValueObject\UserStatus;
use App\System\IdentityAccess\Domain\ValueObject\UserType;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\System\IdentityAccess\Support\AccountFakes;
use App\Tests\Unit\System\IdentityAccess\Support\InMemoryUsers;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ChangeOwnPasswordHandlerTest extends TestCase
{
    private const string ACTIVE   = '01960000-0000-7000-8000-000000004c01';
    private const string DISABLED = '01960000-0000-7000-8000-000000004c02';

    private InMemoryUsers $users;
    private AccountFakes $fakes;

    protected function setUp(): void
    {
        $this->users = new InMemoryUsers();
        $this->fakes = new AccountFakes();

        foreach ([self::ACTIVE => UserStatus::ACTIVE, self::DISABLED => UserStatus::DISABLED] as $id => $status) {
            $this->users->save(User::reconstitute(UserId::fromString($id), $id . '@kiveto.local', 'hashed:ancien-mot-de-passe', new \DateTimeImmutable(), $status, null, UserType::BACKOFFICE));
        }
    }

    public function testTheCurrentPasswordProvesTheHolder(): void
    {
        $this->change(new ChangeOwnPassword(self::ACTIVE, 'ancien-mot-de-passe', 'nouveau-mot-de-passe'));

        self::assertSame('hashed:nouveau-mot-de-passe', $this->users->findById(UserId::fromString(self::ACTIVE))?->passwordHash());
        self::assertSame([self::ACTIVE], $this->users->locked, 'read with a lock');
        self::assertSame(['identity-access.user-password.changed.v1'], $this->fakes->eventNames());
    }

    #[DataProvider('provideAWrongPasswordOrAnUnusableAccountIsRefusedCases')]
    public function testAWrongPasswordOrAnUnusableAccountIsRefused(string $userId, string $current): void
    {
        try {
            $this->change(new ChangeOwnPassword($userId, $current, 'nouveau-mot-de-passe'));
            self::fail('The change was expected to be refused.');
        } catch (InvalidCredentialsException $e) {
            self::assertSame('Invalid email or password.', $e->getMessage());
        }

        self::assertSame('hashed:ancien-mot-de-passe', $this->users->findById(UserId::fromString(self::ACTIVE))?->passwordHash());
        self::assertSame([], $this->fakes->events);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideAWrongPasswordOrAnUnusableAccountIsRefusedCases(): iterable
    {
        yield 'wrong current password' => [self::ACTIVE, 'mauvais-mot-de-passe'];

        yield 'disabled account' => [self::DISABLED, 'ancien-mot-de-passe'];

        yield 'unknown account' => ['01960000-0000-7000-8000-000000004cff', 'ancien-mot-de-passe'];
    }

    public function testAShortNewPasswordIsRefused(): void
    {
        try {
            $this->change(new ChangeOwnPassword(self::ACTIVE, 'ancien-mot-de-passe', 'court'));
            self::fail('A short password was expected to be refused.');
        } catch (UnacceptablePasswordException $e) {
            self::assertSame('Choisissez un mot de passe d’au moins 8 caractères.', $e->getMessage());
        }

        self::assertSame('hashed:ancien-mot-de-passe', $this->users->findById(UserId::fromString(self::ACTIVE))?->passwordHash());
        self::assertSame([], $this->fakes->events);
    }

    private function change(ChangeOwnPassword $command): void
    {
        $verifier = $this->createStub(PasswordHashVerifierInterface::class);
        $verifier->method('verify')->willReturnCallback(static fn (string $plain, string $hash): bool => 'hashed:' . $plain === $hash);

        new ChangeOwnPasswordHandler(
            $this->users,
            $verifier,
            $this->fakes,
            new FrozenClock(new \DateTimeImmutable('2026-11-06 09:00:00', new \DateTimeZone('UTC'))),
            $this->fakes->publisher(),
        )($command);
    }
}
