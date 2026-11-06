<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Application\Command\RegenerateBackupCodes;

use App\System\IdentityAccess\Application\Command\RegenerateBackupCodes\RegenerateBackupCodes;
use App\System\IdentityAccess\Application\Command\RegenerateBackupCodes\RegenerateBackupCodesHandler;
use App\System\IdentityAccess\Domain\SecondFactor\BackupCode;
use App\System\IdentityAccess\Domain\SecondFactor\SecondFactor;
use App\System\IdentityAccess\Domain\SecondFactor\TotpSecret;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use App\Tests\Unit\System\IdentityAccess\Support\SecondFactorFakes;
use PHPUnit\Framework\TestCase;

final class RegenerateBackupCodesHandlerTest extends TestCase
{
    private const string ADMIN = '01960000-0000-7000-8000-000000003c01';

    public function testTenNewCodesReplaceThePreviousOnes(): void
    {
        $fakes = new SecondFactorFakes();
        $at    = new \DateTimeImmutable('2026-11-06 09:00:00', new \DateTimeZone('UTC'));
        $fakes->save(SecondFactor::reconstitute(
            UserId::fromString(self::ADMIN),
            TotpSecret::fromBytes(SecondFactorFakes::RFC_KEY),
            $at,
            null,
            array_map(static fn (int $i): BackupCode => BackupCode::reconstitute('old' . $i, 0 === $i ? $at : null), range(0, 9)),
        ));

        $plain = new RegenerateBackupCodesHandler($fakes, $fakes->backupCodes())(new RegenerateBackupCodes(self::ADMIN));

        self::assertSame('KVT01-ABCDE', $plain[0]);
        self::assertCount(10, $plain);
        self::assertSame([[self::ADMIN], 2], [$fakes->locked, $fakes->saves]);

        $factor = $fakes->factors[self::ADMIN];
        self::assertSame(10, $factor->remainingBackupCodes());
        self::assertSame(
            array_map(static fn (string $code): string => 'h:backup:' . str_replace('-', '', $code), $plain),
            array_map(static fn (BackupCode $code): string => $code->hash(), $factor->backupCodes()),
        );
    }

    public function testAnAccountWithoutSecondFactorHasNoCodesToRegenerate(): void
    {
        $fakes = new SecondFactorFakes();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('This account has no second factor.');

        new RegenerateBackupCodesHandler($fakes, $fakes->backupCodes())(new RegenerateBackupCodes(self::ADMIN));
    }
}
