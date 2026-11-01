<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Application\Command;

use App\Shared\Domain\ValueObject\PhoneNumber;
use App\System\IdentityAccess\Application\Command\RequestPortalOtp\OtpRequestContext;
use App\System\IdentityAccess\Application\Command\RequestPortalOtp\RequestPortalOtp;
use App\System\IdentityAccess\Application\Command\RequestPortalOtp\RequestPortalOtpHandler;
use App\System\IdentityAccess\Domain\User;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\System\IdentityAccess\Support\AccountFakes;
use App\Tests\Unit\System\IdentityAccess\Support\InMemoryOtpChallenges;
use App\Tests\Unit\System\IdentityAccess\Support\InMemoryUsers;
use App\Tests\Unit\System\IdentityAccess\Support\PortalFakes;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * S7a', D13, AC 261: the unknown-number SMS budget in three layers — the
 * client's network, the device hint, the global cap — checked in this
 * order; the first exhausted layer suppresses the SMS and spares the next.
 */
final class RequestPortalOtpUnknownBudgetTest extends TestCase
{
    private const string PHONE = '+33612345678';

    private InMemoryOtpChallenges $challenges;
    private InMemoryUsers $users;
    private AccountFakes $fakes;
    private PortalFakes $portal;

    protected function setUp(): void
    {
        $this->challenges = new InMemoryOtpChallenges();
        $this->users      = new InMemoryUsers();
        $this->fakes      = new AccountFakes();
        $this->portal     = new PortalFakes();
    }

    public function testTheLayersAreSpentInOrder(): void
    {
        $this->request(new OtpRequestContext('203.0.113.77', ' device-1 '));

        self::assertSame(['network:203.0.113.0/24', 'device:device-1', 'global'], $this->portal->budgetCalls);
        self::assertFalse($this->challenges->single()->isSmsSuppressed());
        self::assertCount(1, $this->fakes->portalOtps);
    }

    public function testWithoutDeviceHintTheNetworkAndTheGlobalCapStillApply(): void
    {
        $this->request(new OtpRequestContext('2001:db8:85a3:8d3:1319:8a2e:370:7348', '  '));

        self::assertSame(['network:2001:db8:85a3:8d3::/64', 'global'], $this->portal->budgetCalls);
    }

    public function testWithoutContextOnlyTheGlobalCapApplies(): void
    {
        $this->request(null);

        self::assertSame(['global'], $this->portal->budgetCalls);
    }

    public function testAnExhaustedNetworkSuppressesTheSmsAndSparesTheOtherLayers(): void
    {
        $this->portal->layerBudgets['network:203.0.113.0/24'] = 0;

        $this->request(new OtpRequestContext('203.0.113.77', 'device-1'));

        self::assertSame(['network:203.0.113.0/24'], $this->portal->budgetCalls);
        self::assertSame([true, [], 200], [$this->challenges->single()->isSmsSuppressed(), $this->fakes->portalOtps, $this->portal->budget]);
    }

    public function testAnExhaustedDeviceHintSuppressesTheSms(): void
    {
        $this->portal->layerBudgets['device:device-1'] = 0;

        $this->request(new OtpRequestContext('203.0.113.77', 'device-1'));

        self::assertSame(['network:203.0.113.0/24', 'device:device-1'], $this->portal->budgetCalls);
        self::assertTrue($this->challenges->single()->isSmsSuppressed());
    }

    public function testAnExhaustedGlobalCapSuppressesTheSms(): void
    {
        $this->portal->budget = 0;

        $this->request(new OtpRequestContext('203.0.113.77'));

        self::assertSame(['network:203.0.113.0/24', 'global'], $this->portal->budgetCalls);
        self::assertTrue($this->challenges->single()->isSmsSuppressed());
    }

    public function testAKnownPhoneSpendsNoLayer(): void
    {
        $this->users->save(User::registerPortal(UserId::fromString('01960000-0000-7000-8000-0000000006e1'), PhoneNumber::fromString(self::PHONE), null, new \DateTimeImmutable('2026-11-01')));

        $this->request(new OtpRequestContext('203.0.113.77', 'device-1'));

        self::assertSame([], $this->portal->budgetCalls);
    }

    /**
     * @return iterable<string, array{?string, ?string}>
     */
    public static function provideTheNetworkKeyCases(): iterable
    {
        yield 'IPv4: its /24' => ['192.0.2.200', '192.0.2.0/24'];
        yield 'IPv6: its first 64 bits' => ['2001:db8:1:2:3:4:5:6', '2001:db8:1:2::/64'];
        yield 'IPv6 short form' => ['2001:db8::1', '2001:db8::/64'];
        yield 'IPv4-mapped IPv6: its IPv4 /24' => ['::ffff:198.51.100.9', '198.51.100.0/24'];
        yield 'no IP' => [null, null];
        yield 'not an IP' => ['unknown', null];
    }

    #[DataProvider('provideTheNetworkKeyCases')]
    public function testTheNetworkKey(?string $ip, ?string $expected): void
    {
        self::assertSame($expected, new OtpRequestContext($ip)->networkKey());
    }

    private function request(?OtpRequestContext $context): void
    {
        $handler = new RequestPortalOtpHandler($this->challenges, $this->users, $this->portal, $this->portal, $this->portal, $this->portal, $this->portal, $this->fakes, $this->fakes, new FrozenClock(new \DateTimeImmutable('2026-11-02 09:00:00')));

        $handler(new RequestPortalOtp(self::PHONE, 'login', requestContext: $context));
    }
}
