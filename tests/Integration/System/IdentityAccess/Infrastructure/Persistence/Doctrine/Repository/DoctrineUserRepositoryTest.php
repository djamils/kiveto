<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Repository;

use App\System\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\System\IdentityAccess\Domain\User;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use App\System\IdentityAccess\Domain\ValueObject\UserStatus;
use App\System\IdentityAccess\Domain\ValueObject\UserType;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * The account lifecycle through the real table: an invited user is inserted,
 * then activated in place; a sign-in is stamped.
 */
final class DoctrineUserRepositoryTest extends KernelTestCase
{
    private const string USER = '01960000-0000-7000-8000-00000000ad01';

    public function testAnInvitedUserIsActivatedInPlace(): void
    {
        $users = self::getContainer()->get(UserRepositoryInterface::class);
        \assert($users instanceof UserRepositoryInterface);

        $users->save(User::invite(UserId::fromString(self::USER), 'hugo.invite@example.com', new \DateTimeImmutable('2026-10-26 10:00:00'), UserType::CLINIC));
        $this->em()->clear();

        $user = $users->findById(UserId::fromString(self::USER));
        self::assertNotNull($user);
        self::assertSame(UserStatus::PENDING, $user->status());

        $user->activate('$chosen', new \DateTimeImmutable('2026-10-27 08:00:00'));
        $users->save($user);
        $this->em()->clear();

        $row = $this->connection()->fetchAssociative('SELECT status, password_hash, email_verified_at, version FROM identity_access__users WHERE id = ?', [Uuid::fromString(self::USER)->toBinary()]);
        self::assertSame(['status' => 'ACTIVE', 'password_hash' => '$chosen', 'email_verified_at' => '2026-10-27 08:00:00', 'version' => 2], $row);

        $users->recordLogin(UserId::fromString(self::USER), new \DateTimeImmutable('2026-10-27 08:05:00'));
        self::assertSame('2026-10-27 08:05:00', $this->connection()->fetchOne('SELECT last_login_at FROM identity_access__users WHERE id = ?', [Uuid::fromString(self::USER)->toBinary()]));
    }

    private function em(): EntityManagerInterface
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);

        return $em;
    }

    private function connection(): Connection
    {
        $connection = self::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);

        return $connection;
    }
}
