<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Domain\SecondFactor;

use App\System\IdentityAccess\Domain\SecondFactor\BackupCode;
use App\System\IdentityAccess\Domain\SecondFactor\InvalidSecondFactorCodeException;
use App\System\IdentityAccess\Domain\SecondFactor\SecondFactor;
use App\System\IdentityAccess\Domain\SecondFactor\Totp;
use App\System\IdentityAccess\Domain\SecondFactor\TotpSecret;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

final class SecondFactorTest extends TestCase
{
    private const string USER = '01960000-0000-7000-8000-000000002fa1';

    public function testTheTypedCodeProvesTheKeyAndEnablesTheFactor(): void
    {
        $at     = new \DateTimeImmutable('@1111111111');
        $factor = SecondFactor::enable(UserId::fromString(self::USER), self::secret(), '050 471', self::hashes('a'), $at);

        self::assertSame(self::USER, $factor->userId()->toString());
        self::assertTrue($factor->secret()->equals(self::secret()));
        self::assertSame($at, $factor->enabledAt());
        self::assertSame(Totp::stepAt($at), $factor->lastUsedStep(), 'the confirmation code is spent');
        self::assertSame(self::hashes('a'), array_map(static fn (BackupCode $code): string => $code->hash(), $factor->backupCodes()));
        self::assertSame([10, 10], [$factor->remainingBackupCodes(), SecondFactor::BACKUP_CODE_COUNT]);
        self::assertFalse($factor->verifyCode('050471', $at), 'the confirmation code cannot sign in');
    }

    public function testAWrongConfirmationCodeEnablesNothing(): void
    {
        $this->expectException(InvalidSecondFactorCodeException::class);
        $this->expectExceptionMessage('Ce code n’est pas valide. Vérifie l’heure de ton téléphone et réessaie.');

        SecondFactor::enable(UserId::fromString(self::USER), self::secret(), '000000', self::hashes('a'), new \DateTimeImmutable('@1111111111'));
    }

    public function testACodeOfTheAppIsAcceptedOnce(): void
    {
        $now    = new \DateTimeImmutable('@1111111111');
        $factor = $this->factor(null);

        self::assertFalse($factor->verifyCode('123456', $now));
        self::assertNull($factor->lastUsedStep(), 'a refused code spends nothing');

        self::assertTrue($factor->verifyCode('050471', $now));
        self::assertSame(Totp::stepAt($now), $factor->lastUsedStep());
        self::assertFalse($factor->verifyCode('050471', $now), 'replayed');

        $next = $now->modify('+30 seconds');
        self::assertTrue($factor->verifyCode(Totp::code(self::secret(), Totp::stepAt($next)), $next));
        self::assertSame(Totp::stepAt($next), $factor->lastUsedStep());
    }

    public function testABackupCodeIsSpentOnce(): void
    {
        $at     = new \DateTimeImmutable('2026-11-06 09:00:00', new \DateTimeZone('UTC'));
        $factor = $this->factor(null);

        self::assertFalse($factor->useBackupCode('unknown', $at));
        self::assertTrue($factor->useBackupCode('a3', $at));
        self::assertFalse($factor->useBackupCode('a3', $at), 'already spent');

        self::assertSame(9, $factor->remainingBackupCodes());
        self::assertSame($at, $factor->backupCodes()[3]->usedAt());
        self::assertNull($factor->backupCodes()[4]->usedAt());
    }

    public function testRegeneratingReplacesEveryBackupCode(): void
    {
        $at     = new \DateTimeImmutable('2026-11-06 09:00:00', new \DateTimeZone('UTC'));
        $factor = $this->factor(null);
        $factor->useBackupCode('a0', $at);

        $factor->replaceBackupCodes(self::hashes('b'));

        self::assertSame(10, $factor->remainingBackupCodes());
        self::assertFalse($factor->useBackupCode('a1', $at), 'the previous codes stop working');
        self::assertTrue($factor->useBackupCode('b1', $at));
    }

    public function testTenDistinctBackupCodesAreNeeded(): void
    {
        $factor = $this->factor(null);

        foreach ([\array_slice(self::hashes('b'), 0, 9), [...\array_slice(self::hashes('b'), 0, 9), 'b0'], [...self::hashes('b'), 'b10']] as $hashes) {
            try {
                $factor->replaceBackupCodes($hashes);
                self::fail('Backup codes that are not ten distinct ones were expected to be refused.');
            } catch (\InvalidArgumentException $e) {
                self::assertSame('A second factor comes with 10 distinct backup codes.', $e->getMessage());
            }
        }

        self::assertSame(self::hashes('a'), array_map(static fn (BackupCode $code): string => $code->hash(), $factor->backupCodes()));
    }

    public function testAReconstitutedFactorKeepsItsState(): void
    {
        $enabledAt = new \DateTimeImmutable('2026-11-01 08:00:00', new \DateTimeZone('UTC'));
        $usedAt    = new \DateTimeImmutable('2026-11-02 08:00:00', new \DateTimeZone('UTC'));
        $factor    = SecondFactor::reconstitute(UserId::fromString(self::USER), self::secret(), $enabledAt, 42, [BackupCode::reconstitute('x', $usedAt), BackupCode::unused('y')]);

        self::assertSame([self::USER, $enabledAt, 42, 1], [$factor->userId()->toString(), $factor->enabledAt(), $factor->lastUsedStep(), $factor->remainingBackupCodes()]);
        self::assertSame($usedAt, $factor->backupCodes()[0]->usedAt());
    }

    private function factor(?int $lastUsedStep): SecondFactor
    {
        return SecondFactor::reconstitute(
            UserId::fromString(self::USER),
            self::secret(),
            new \DateTimeImmutable('2026-11-01 08:00:00', new \DateTimeZone('UTC')),
            $lastUsedStep,
            array_map(BackupCode::unused(...), self::hashes('a')),
        );
    }

    /**
     * @return list<string>
     */
    private static function hashes(string $prefix): array
    {
        return array_map(static fn (int $i): string => $prefix . $i, range(0, 9));
    }

    private static function secret(): TotpSecret
    {
        return TotpSecret::fromBytes('12345678901234567890');
    }
}
