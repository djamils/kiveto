<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Application\Query\GetSecondFactorStatus;

use App\System\IdentityAccess\Application\Query\GetSecondFactorStatus\GetSecondFactorStatus;
use App\System\IdentityAccess\Application\Query\GetSecondFactorStatus\GetSecondFactorStatusHandler;
use App\System\IdentityAccess\Domain\SecondFactor\BackupCode;
use App\System\IdentityAccess\Domain\SecondFactor\SecondFactor;
use App\System\IdentityAccess\Domain\SecondFactor\TotpSecret;
use App\System\IdentityAccess\Domain\SecondFactor\TrustedDeviceRepositoryInterface;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\System\IdentityAccess\Support\SecondFactorFakes;
use PHPUnit\Framework\TestCase;

final class GetSecondFactorStatusHandlerTest extends TestCase
{
    private const string ADMIN = '01960000-0000-7000-8000-000000004d01';

    private FrozenClock $clock;

    protected function setUp(): void
    {
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-11-06 09:00:00', new \DateTimeZone('UTC')));
    }

    public function testAnEnabledFactorShowsItsRemainingCodesAndTrustedBrowsers(): void
    {
        $fakes     = new SecondFactorFakes();
        $enabledAt = new \DateTimeImmutable('2026-11-01 08:00:00', new \DateTimeZone('UTC'));
        $fakes->save(SecondFactor::reconstitute(
            UserId::fromString(self::ADMIN),
            TotpSecret::fromBytes(SecondFactorFakes::RFC_KEY),
            $enabledAt,
            null,
            array_map(fn (int $i): BackupCode => BackupCode::reconstitute('c' . $i, $i < 3 ? $this->clock->now() : null), range(0, 9)),
        ));

        $status = new GetSecondFactorStatusHandler($fakes, $this->devices(2), $this->clock)(new GetSecondFactorStatus(self::ADMIN));

        self::assertSame([true, $enabledAt, 7, 10, 2], [$status->enabled, $status->enabledAt, $status->remainingBackupCodes, $status->backupCodeCount, $status->trustedDevices]);
    }

    public function testAnAccountWithoutFactorShowsItDisabled(): void
    {
        $status = new GetSecondFactorStatusHandler(new SecondFactorFakes(), $this->devices(0), $this->clock)(new GetSecondFactorStatus(self::ADMIN));

        self::assertSame([false, null, 0, 10, 0], [$status->enabled, $status->enabledAt, $status->remainingBackupCodes, $status->backupCodeCount, $status->trustedDevices]);
    }

    private function devices(int $usable): TrustedDeviceRepositoryInterface
    {
        $devices = $this->createMock(TrustedDeviceRepositoryInterface::class);
        $devices->expects(self::once())->method('countUsable')
            ->with(self::callback(static fn (UserId $id): bool => self::ADMIN === $id->toString()), $this->clock->now())
            ->willReturn($usable)
        ;

        return $devices;
    }
}
