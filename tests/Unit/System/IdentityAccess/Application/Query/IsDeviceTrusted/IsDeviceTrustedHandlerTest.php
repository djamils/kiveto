<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Application\Query\IsDeviceTrusted;

use App\Shared\Infrastructure\Security\OpaqueTokenHasher;
use App\System\IdentityAccess\Application\Query\IsDeviceTrusted\IsDeviceTrusted;
use App\System\IdentityAccess\Application\Query\IsDeviceTrusted\IsDeviceTrustedHandler;
use App\System\IdentityAccess\Domain\SecondFactor\TrustedDevice;
use App\System\IdentityAccess\Domain\SecondFactor\TrustedDeviceRepositoryInterface;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use App\Tests\Shared\Time\FrozenClock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IsDeviceTrustedHandlerTest extends TestCase
{
    private const string ADMIN  = '01960000-0000-7000-8000-000000004e01';
    private const string OTHER  = '01960000-0000-7000-8000-000000004e02';
    private const string SECRET = 'cookie-secret';
    private const string NOW    = '2026-11-06 09:00:00';

    #[DataProvider('provideOnlyAUsableBrowserOfTheAccountIsTrustedCases')]
    public function testOnlyAUsableBrowserOfTheAccountIsTrusted(?TrustedDevice $device, bool $trusted): void
    {
        $devices = $this->createMock(TrustedDeviceRepositoryInterface::class);
        $devices->expects(self::once())->method('findByTokenHash')->with(hash('sha256', self::SECRET))->willReturn($device);

        self::assertSame($trusted, $this->ask($devices, self::SECRET));
    }

    /**
     * @return iterable<string, array{?TrustedDevice, bool}>
     */
    public static function provideOnlyAUsableBrowserOfTheAccountIsTrustedCases(): iterable
    {
        $now = new \DateTimeImmutable(self::NOW, new \DateTimeZone('UTC'));

        yield 'usable' => [self::device(self::ADMIN, $now->modify('-29 days'), null), true];

        yield 'unknown cookie' => [null, false];

        yield 'trusted for another account' => [self::device(self::OTHER, $now->modify('-1 day'), null), false];

        yield 'expired' => [self::device(self::ADMIN, $now->modify('-30 days'), null), false];

        yield 'revoked' => [self::device(self::ADMIN, $now->modify('-1 day'), $now->modify('-1 hour')), false];
    }

    public function testABrowserWithoutCookieIsNotLookedUp(): void
    {
        $devices = $this->createMock(TrustedDeviceRepositoryInterface::class);
        $devices->expects(self::never())->method('findByTokenHash');

        self::assertFalse($this->ask($devices, ''));
    }

    private function ask(TrustedDeviceRepositoryInterface $devices, string $secret): bool
    {
        return new IsDeviceTrustedHandler($devices, new OpaqueTokenHasher(), new FrozenClock(new \DateTimeImmutable(self::NOW, new \DateTimeZone('UTC'))))(
            new IsDeviceTrusted(self::ADMIN, $secret),
        );
    }

    private static function device(string $userId, \DateTimeImmutable $trustedAt, ?\DateTimeImmutable $revokedAt): TrustedDevice
    {
        return TrustedDevice::reconstitute(
            '01960000-0000-7000-8000-000000004ea1',
            UserId::fromString($userId),
            hash('sha256', self::SECRET),
            'Firefox',
            $trustedAt,
            $trustedAt->modify('+30 days'),
            $revokedAt,
        );
    }
}
