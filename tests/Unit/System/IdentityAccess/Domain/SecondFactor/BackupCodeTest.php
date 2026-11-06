<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Domain\SecondFactor;

use App\System\IdentityAccess\Domain\SecondFactor\BackupCode;
use PHPUnit\Framework\TestCase;

final class BackupCodeTest extends TestCase
{
    public function testAFreshCodeIsUnused(): void
    {
        $code = BackupCode::unused('digest');

        self::assertSame(['digest', null, false], [$code->hash(), $code->usedAt(), $code->isUsed()]);
    }

    public function testUsingACodeGivesASpentCopy(): void
    {
        $code = BackupCode::unused('digest');
        $at   = new \DateTimeImmutable('2026-11-06 09:00:00', new \DateTimeZone('UTC'));

        $used = $code->use($at);

        self::assertSame(['digest', $at, true], [$used->hash(), $used->usedAt(), $used->isUsed()]);
        self::assertFalse($code->isUsed(), 'the original is left as it was');
    }

    public function testAReconstitutedCodeKeepsItsUse(): void
    {
        $at = new \DateTimeImmutable('2026-11-06 09:00:00', new \DateTimeZone('UTC'));

        self::assertSame($at, BackupCode::reconstitute('digest', $at)->usedAt());
    }

    public function testACodeNeedsItsDigest(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A backup code needs its digest.');

        BackupCode::unused('');
    }
}
