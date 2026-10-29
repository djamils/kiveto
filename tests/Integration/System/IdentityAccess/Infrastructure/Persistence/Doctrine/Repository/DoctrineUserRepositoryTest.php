<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Repository;

use App\Shared\Domain\ValueObject\PhoneNumber;
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
    private const string USER  = '01960000-0000-7000-8000-00000000ad01';
    private const string OWNER = '01960000-0000-7000-8000-00000000ad02';
    private const string OTHER = '01960000-0000-7000-8000-00000000ad03';

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

    /**
     * AC 4: e-mail and phone are unique per account type — a veterinarian may
     * own a pet with the same address, two owners never share a phone.
     */
    public function testAddressesAndPhonesAreUniquePerAccountType(): void
    {
        $users = $this->users();
        $users->save(User::register(UserId::fromString(self::USER), 'vet@clinique.fr', '$h', new \DateTimeImmutable('2026-10-29 10:00:00'), UserType::CLINIC));

        $owner = User::registerPortal(UserId::fromString(self::OWNER), PhoneNumber::fromString('+33612345601'), 'vet@clinique.fr', new \DateTimeImmutable('2026-10-29 10:00:00'));
        self::assertTrue($users->addPortalIfPhoneFree($owner), 'the same address on another account type');

        $samePhone = User::registerPortal(UserId::fromString(self::OTHER), PhoneNumber::fromString('+33612345601'), null, new \DateTimeImmutable());
        self::assertFalse($users->addPortalIfPhoneFree($samePhone), 'the phone is taken');

        $sameEmail = User::registerPortal(UserId::fromString(self::OTHER), PhoneNumber::fromString('+33612345602'), 'vet@clinique.fr', new \DateTimeImmutable());
        self::assertFalse($users->addPortalIfPhoneFree($sameEmail), 'the owner address is taken');
        self::assertTrue($this->em()->isOpen(), 'a duplicate never closes the entity manager');

        $found = $users->findByPhoneAndType(PhoneNumber::fromString('+33612345601'), UserType::PORTAL);
        self::assertSame([self::OWNER, 'vet@clinique.fr', UserStatus::ACTIVE, '2026-10-29 10:00:00'], [
            $found?->id()->toString(), $found?->email(), $found?->status(), $found?->phoneVerifiedAt()?->format('Y-m-d H:i:s'),
        ]);
        self::assertSame(self::USER, $users->findByEmailAndType('vet@clinique.fr', UserType::CLINIC)?->id()->toString());
        self::assertSame(self::OWNER, $users->findByEmailAndType('vet@clinique.fr', UserType::PORTAL)?->id()->toString());
        self::assertNull($users->findByPhoneAndType(PhoneNumber::fromString('+33612345601'), UserType::CLINIC));

        $this->em()->wrapInTransaction(static function () use ($users): void {
            self::assertSame(self::OWNER, $users->findByPhoneAndTypeForUpdate(PhoneNumber::fromString('+33612345601'), UserType::PORTAL)?->id()->toString());
            self::assertNull($users->findByEmailAndTypeForUpdate('vet@clinique.fr', UserType::BACKOFFICE));
        });
    }

    public function testThePhoneMutexIsAnIdempotentUpsert(): void
    {
        $users = $this->users();
        $users->lockPortalPhone(PhoneNumber::fromString('+33612345603'));
        $users->lockPortalPhone(PhoneNumber::fromString('+33612345603'));

        $count = $this->connection()->fetchOne('SELECT COUNT(*) FROM identity_access__portal_phone_locks WHERE phone = ?', ['+33612345603']);
        \assert(is_numeric($count));
        self::assertSame(1, (int) $count);
    }

    /** AC 65: a legacy owner row without phone, disabled by the migration, still loads. */
    public function testALegacyOwnerWithoutPhoneLoads(): void
    {
        $this->connection()->executeStatement(
            'INSERT INTO identity_access__users (id, email, password_hash, created_at, status, type, version) VALUES (?, ?, ?, ?, ?, ?, 1)',
            [Uuid::fromString(self::OTHER)->toBinary(), 'legacy@example.com', '$h', '2026-01-01 00:00:00', 'DISABLED', 'PORTAL'],
        );

        $user = $this->users()->findById(UserId::fromString(self::OTHER));
        self::assertSame([null, UserStatus::DISABLED, UserType::PORTAL], [$user?->phone(), $user?->status(), $user?->type()]);
    }

    private function users(): UserRepositoryInterface
    {
        $users = self::getContainer()->get(UserRepositoryInterface::class);
        \assert($users instanceof UserRepositoryInterface);

        return $users;
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
