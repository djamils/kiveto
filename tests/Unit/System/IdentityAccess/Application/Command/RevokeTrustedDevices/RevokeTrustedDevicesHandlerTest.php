<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Application\Command\RevokeTrustedDevices;

use App\System\IdentityAccess\Application\Command\RevokeTrustedDevices\RevokeTrustedDevices;
use App\System\IdentityAccess\Application\Command\RevokeTrustedDevices\RevokeTrustedDevicesHandler;
use App\System\IdentityAccess\Domain\SecondFactor\TrustedDeviceRepositoryInterface;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use App\Tests\Shared\Time\FrozenClock;
use PHPUnit\Framework\TestCase;

final class RevokeTrustedDevicesHandlerTest extends TestCase
{
    private const string ADMIN = '01960000-0000-7000-8000-000000003e01';

    public function testEveryTrustedBrowserOfTheAccountIsRevokedNow(): void
    {
        $now     = new \DateTimeImmutable('2026-11-06 09:00:00', new \DateTimeZone('UTC'));
        $devices = $this->createMock(TrustedDeviceRepositoryInterface::class);
        $devices->expects(self::once())->method('revokeAll')
            ->with(self::callback(static fn (UserId $id): bool => self::ADMIN === $id->toString()), $now)
            ->willReturn(3)
        ;

        self::assertSame(3, new RevokeTrustedDevicesHandler($devices, new FrozenClock($now))(new RevokeTrustedDevices(self::ADMIN)));
    }
}
