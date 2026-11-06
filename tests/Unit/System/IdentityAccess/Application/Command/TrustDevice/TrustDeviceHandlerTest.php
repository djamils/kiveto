<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Application\Command\TrustDevice;

use App\Shared\Infrastructure\Security\OpaqueTokenHasher;
use App\System\IdentityAccess\Application\Command\TrustDevice\TrustDevice;
use App\System\IdentityAccess\Application\Command\TrustDevice\TrustDeviceHandler;
use App\System\IdentityAccess\Domain\SecondFactor\TrustedDevice;
use App\System\IdentityAccess\Domain\SecondFactor\TrustedDeviceRepositoryInterface;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\System\IdentityAccess\Support\AccountFakes;
use PHPUnit\Framework\TestCase;

final class TrustDeviceHandlerTest extends TestCase
{
    private const string ADMIN = '01960000-0000-7000-8000-000000003d01';

    public function testTheBrowserIsTrustedForThirtyDaysByTheDigestOfItsCookie(): void
    {
        $now     = new \DateTimeImmutable('2026-11-06 09:00:00', new \DateTimeZone('UTC'));
        $devices = $this->createMock(TrustedDeviceRepositoryInterface::class);
        $added   = null;
        $devices->expects(self::once())->method('add')->willReturnCallback(static function (TrustedDevice $device) use (&$added): void {
            $added = $device;
        });

        $secret = new TrustDeviceHandler($devices, new OpaqueTokenHasher(), new AccountFakes(), new FrozenClock($now))(new TrustDevice(self::ADMIN, ' Firefox sur Windows '));

        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $secret);
        self::assertInstanceOf(TrustedDevice::class, $added);
        self::assertSame(['01960000-0000-7000-8000-000000000001', self::ADMIN, hash('sha256', $secret), 'Firefox sur Windows', $now, '2026-12-06 09:00:00', null], [
            $added->id(), $added->userId()->toString(), $added->tokenHash(), $added->label(),
            $added->createdAt(), $added->expiresAt()->format('Y-m-d H:i:s'), $added->revokedAt(),
        ]);
    }
}
