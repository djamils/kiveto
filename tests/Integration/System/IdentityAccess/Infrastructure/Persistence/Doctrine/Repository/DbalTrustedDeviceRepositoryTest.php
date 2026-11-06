<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Repository;

use App\System\IdentityAccess\Domain\SecondFactor\TrustedDevice;
use App\System\IdentityAccess\Domain\SecondFactor\TrustedDeviceRepositoryInterface;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DbalTrustedDeviceRepositoryTest extends KernelTestCase
{
    private const string ADMIN = '01960000-0000-7000-8000-000000006c01';
    private const string OTHER = '01960000-0000-7000-8000-000000006c02';

    public function testATrustedBrowserIsReadBackByTheDigestOfItsCookie(): void
    {
        $devices = $this->devices();
        // A Paris time: the dates are stored in UTC and read back as UTC.
        $devices->add(TrustedDevice::trust('01960000-0000-7000-8000-000000006ca1', UserId::fromString(self::ADMIN), hash('sha256', 'c1'), 'Firefox sur Windows', new \DateTimeImmutable('2026-11-06 10:00:00', new \DateTimeZone('Europe/Paris'))));

        self::assertNull($devices->findByTokenHash(hash('sha256', 'unknown')));

        $found = $devices->findByTokenHash(hash('sha256', 'c1'));
        self::assertNotNull($found);
        self::assertSame(['01960000-0000-7000-8000-000000006ca1', self::ADMIN, hash('sha256', 'c1'), 'Firefox sur Windows', '2026-11-06 09:00:00', '2026-12-06 09:00:00', 'UTC', null], [
            $found->id(), $found->userId()->toString(), $found->tokenHash(), $found->label(),
            $found->createdAt()->format('Y-m-d H:i:s'), $found->expiresAt()->format('Y-m-d H:i:s'), $found->expiresAt()->getTimezone()->getName(), $found->revokedAt(),
        ]);
    }

    public function testOnlyTheUsableBrowsersOfTheAccountAreCountedAndRevoked(): void
    {
        $devices = $this->devices();
        $now     = new \DateTimeImmutable('2026-11-06 09:00:00', new \DateTimeZone('UTC'));
        $admin   = UserId::fromString(self::ADMIN);
        $devices->add(TrustedDevice::trust('01960000-0000-7000-8000-000000006ca1', $admin, hash('sha256', 'usable'), 'Firefox', $now->modify('-1 day')));
        $devices->add(TrustedDevice::trust('01960000-0000-7000-8000-000000006ca2', $admin, hash('sha256', 'also-usable'), 'Safari', $now->modify('-29 days')));
        $devices->add(TrustedDevice::trust('01960000-0000-7000-8000-000000006ca3', $admin, hash('sha256', 'expired'), 'Edge', $now->modify('-30 days')));
        $devices->add(TrustedDevice::reconstitute('01960000-0000-7000-8000-000000006ca4', $admin, hash('sha256', 'revoked'), 'Chrome', $now->modify('-2 days'), $now->modify('+28 days'), $now->modify('-1 day')));
        $devices->add(TrustedDevice::trust('01960000-0000-7000-8000-000000006ca5', UserId::fromString(self::OTHER), hash('sha256', 'other'), 'Firefox', $now->modify('-1 day')));

        self::assertSame(2, $devices->countUsable($admin, $now));
        self::assertSame(2, $devices->revokeAll($admin, $now));
        self::assertSame(0, $devices->revokeAll($admin, $now->modify('+1 minute')), 'already revoked');
        self::assertSame(0, $devices->countUsable($admin, $now));

        self::assertSame('2026-11-06 09:00:00', $devices->findByTokenHash(hash('sha256', 'usable'))?->revokedAt()?->format('Y-m-d H:i:s'));
        self::assertNull($devices->findByTokenHash(hash('sha256', 'expired'))?->revokedAt(), 'an expired browser is left as it was');
        self::assertSame('2026-11-05 09:00:00', $devices->findByTokenHash(hash('sha256', 'revoked'))?->revokedAt()?->format('Y-m-d H:i:s'));
        self::assertSame(1, $devices->countUsable(UserId::fromString(self::OTHER), $now), 'another account is untouched');
    }

    private function devices(): TrustedDeviceRepositoryInterface
    {
        $repository = self::getContainer()->get(TrustedDeviceRepositoryInterface::class);
        \assert($repository instanceof TrustedDeviceRepositoryInterface);

        return $repository;
    }
}
