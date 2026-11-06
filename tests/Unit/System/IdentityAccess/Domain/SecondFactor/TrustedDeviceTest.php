<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Domain\SecondFactor;

use App\System\IdentityAccess\Domain\SecondFactor\TrustedDevice;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

final class TrustedDeviceTest extends TestCase
{
    private const string ID   = '01960000-0000-7000-8000-000000002fd1';
    private const string USER = '01960000-0000-7000-8000-000000002fa1';

    public function testATrustedBrowserSkipsTheCodeForThirtyDays(): void
    {
        $at     = new \DateTimeImmutable('2026-11-06 09:00:00', new \DateTimeZone('UTC'));
        $device = TrustedDevice::trust(self::ID, UserId::fromString(self::USER), 'digest', '  Firefox sur Windows  ', $at);

        self::assertSame([self::ID, self::USER, 'digest', 'Firefox sur Windows', $at, '2026-12-06 09:00:00', null], [
            $device->id(), $device->userId()->toString(), $device->tokenHash(), $device->label(),
            $device->createdAt(), $device->expiresAt()->format('Y-m-d H:i:s'), $device->revokedAt(),
        ]);
        self::assertSame(30, TrustedDevice::DAYS);

        self::assertTrue($device->isUsableAt($at));
        self::assertTrue($device->isUsableAt($at->modify('+30 days -1 second')));
        self::assertFalse($device->isUsableAt($at->modify('+30 days')), 'expired');
    }

    public function testALongLabelIsCutAt120Characters(): void
    {
        $device = TrustedDevice::trust(self::ID, UserId::fromString(self::USER), 'digest', str_repeat('é', 130), new \DateTimeImmutable());

        self::assertSame(str_repeat('é', 120), $device->label());
    }

    public function testARevokedBrowserAsksTheCodeAgain(): void
    {
        $createdAt = new \DateTimeImmutable('2026-11-06 09:00:00', new \DateTimeZone('UTC'));
        $revokedAt = $createdAt->modify('+1 day');
        $device    = TrustedDevice::reconstitute(self::ID, UserId::fromString(self::USER), 'digest', 'Safari', $createdAt, $createdAt->modify('+30 days'), $revokedAt);

        self::assertSame($revokedAt, $device->revokedAt());
        self::assertFalse($device->isUsableAt($createdAt->modify('+2 days')));
    }
}
