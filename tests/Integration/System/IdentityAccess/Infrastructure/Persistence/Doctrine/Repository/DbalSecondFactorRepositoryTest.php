<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Repository;

use App\System\IdentityAccess\Domain\SecondFactor\BackupCode;
use App\System\IdentityAccess\Domain\SecondFactor\SecondFactor;
use App\System\IdentityAccess\Domain\SecondFactor\SecondFactorRepositoryInterface;
use App\System\IdentityAccess\Domain\SecondFactor\Totp;
use App\System\IdentityAccess\Domain\SecondFactor\TotpSecret;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use App\System\IdentityAccess\Infrastructure\Security\TotpSecretCipher;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class DbalSecondFactorRepositoryTest extends KernelTestCase
{
    private const string ADMIN = '01960000-0000-7000-8000-000000006b01';
    private const string OTHER = '01960000-0000-7000-8000-000000006b02';

    public function testAnEnabledFactorIsReadBackWholly(): void
    {
        $factors = $this->factors();
        // A Paris time: the dates are stored in UTC and read back as UTC.
        $at = new \DateTimeImmutable('2026-11-06 10:00:00', new \DateTimeZone('Europe/Paris'));
        $factors->save($this->enable(self::ADMIN, $at));

        self::assertNull($factors->findByUserId(UserId::fromString(self::OTHER)));

        $found = $factors->findByUserId(UserId::fromString(self::ADMIN));
        self::assertNotNull($found);
        self::assertSame([self::ADMIN, '2026-11-06 09:00:00', 'UTC', Totp::stepAt($at), 10], [
            $found->userId()->toString(), $found->enabledAt()->format('Y-m-d H:i:s'), $found->enabledAt()->getTimezone()->getName(),
            $found->lastUsedStep(), $found->remainingBackupCodes(),
        ]);
        self::assertTrue($found->secret()->equals(self::secret()));
        self::assertSame(self::hashes(), array_map(static fn (BackupCode $code): string => $code->hash(), $found->backupCodes()));
        self::assertSame(array_fill(0, 10, null), array_map(static fn (BackupCode $code): ?\DateTimeImmutable => $code->usedAt(), $found->backupCodes()));
    }

    public function testTheKeyIsEncryptedAtRest(): void
    {
        $this->factors()->save($this->enable(self::ADMIN, new \DateTimeImmutable('2026-11-06 09:00:00', new \DateTimeZone('UTC'))));

        $stored = $this->connection()->fetchOne(
            'SELECT secret_ciphertext FROM identity_access__second_factors WHERE user_id = :user',
            ['user' => Uuid::fromString(self::ADMIN)->toBinary()],
        );

        self::assertIsString($stored);
        self::assertNotSame(self::secret()->toBase32(), $stored);
        self::assertStringNotContainsString(self::secret()->toBase32(), $stored);
        self::assertStringNotContainsString(self::secret()->bytes(), $stored);

        $kernelSecret = self::getContainer()->getParameter('kernel.secret');
        self::assertIsString($kernelSecret);
        self::assertSame(self::secret()->bytes(), new TotpSecretCipher($kernelSecret)->decrypt($stored));
    }

    public function testASavedFactorKeepsItsLastStepAndSpentCodes(): void
    {
        $factors = $this->factors();
        $at      = new \DateTimeImmutable('2026-11-06 09:00:00', new \DateTimeZone('UTC'));
        $factors->save($this->enable(self::ADMIN, $at));

        $later  = $at->modify('+5 minutes');
        $factor = $this->connection()->transactional(static fn (): ?SecondFactor => $factors->findByUserIdForUpdate(UserId::fromString(self::ADMIN)));
        self::assertNotNull($factor);
        self::assertTrue($factor->verifyCode(Totp::code(self::secret(), Totp::stepAt($later)), $later));
        self::assertTrue($factor->useBackupCode('digest-3', new \DateTimeImmutable('2026-11-06 10:05:00', new \DateTimeZone('Europe/Paris'))));
        $factors->save($factor);

        $found = $factors->findByUserIdForUpdate(UserId::fromString(self::ADMIN));
        self::assertNotNull($found);
        self::assertSame([Totp::stepAt($later), 9, '2026-11-06 09:00:00'], [$found->lastUsedStep(), $found->remainingBackupCodes(), $found->enabledAt()->format('Y-m-d H:i:s')]);
        self::assertSame(['2026-11-06 09:05:00', 'UTC'], [$found->backupCodes()[3]->usedAt()?->format('Y-m-d H:i:s'), $found->backupCodes()[3]->usedAt()?->getTimezone()->getName()]);
        self::assertNull($found->backupCodes()[2]->usedAt());

        $found->replaceBackupCodes(array_map(static fn (int $i): string => 'fresh-' . $i, range(0, 9)));
        $factors->save($found);
        self::assertSame(10, $factors->findByUserId(UserId::fromString(self::ADMIN))?->remainingBackupCodes());
    }

    public function testAFactorWithoutUsedStepIsReadBackSo(): void
    {
        $factors = $this->factors();
        $at      = new \DateTimeImmutable('2026-11-06 09:00:00', new \DateTimeZone('UTC'));
        $factors->save(SecondFactor::reconstitute(UserId::fromString(self::ADMIN), self::secret(), $at, null, array_map(BackupCode::unused(...), self::hashes())));

        self::assertNull($factors->findByUserId(UserId::fromString(self::ADMIN))?->lastUsedStep());
    }

    public function testDeletingAFactorLeavesTheOthers(): void
    {
        $factors = $this->factors();
        $at      = new \DateTimeImmutable('2026-11-06 09:00:00', new \DateTimeZone('UTC'));
        $factors->save($this->enable(self::ADMIN, $at));
        $factors->save($this->enable(self::OTHER, $at));

        $factors->delete(UserId::fromString(self::ADMIN));

        self::assertNull($factors->findByUserId(UserId::fromString(self::ADMIN)));
        self::assertNotNull($factors->findByUserId(UserId::fromString(self::OTHER)));
    }

    private function enable(string $userId, \DateTimeImmutable $at): SecondFactor
    {
        return SecondFactor::enable(UserId::fromString($userId), self::secret(), Totp::code(self::secret(), Totp::stepAt($at)), self::hashes(), $at);
    }

    /**
     * @return list<string>
     */
    private static function hashes(): array
    {
        return array_map(static fn (int $i): string => 'digest-' . $i, range(0, 9));
    }

    private static function secret(): TotpSecret
    {
        return TotpSecret::fromBytes('12345678901234567890');
    }

    private function factors(): SecondFactorRepositoryInterface
    {
        $repository = self::getContainer()->get(SecondFactorRepositoryInterface::class);
        \assert($repository instanceof SecondFactorRepositoryInterface);

        return $repository;
    }

    private function connection(): Connection
    {
        $connection = self::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);

        return $connection;
    }
}
