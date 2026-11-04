<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Application\Command;

use App\Shared\Domain\ValueObject\PhoneNumber;
use App\System\IdentityAccess\Application\Command\RequestPortalOtp\OtpChallengeIssued;
use App\System\IdentityAccess\Application\Command\RequestPortalOtp\OtpRequestContext;
use App\System\IdentityAccess\Application\Command\RequestPortalOtp\RequestPortalOtp;
use App\System\IdentityAccess\Application\Command\RequestPortalOtp\RequestPortalOtpHandler;
use App\System\IdentityAccess\Application\Command\ResendPortalOtp\ResendPortalOtp;
use App\System\IdentityAccess\Application\Command\ResendPortalOtp\ResendPortalOtpHandler;
use App\System\IdentityAccess\Application\Service\UnknownNumberSmsGate;
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
 * S7a', D13, AC 261, S7-05: the unknown-number SMS budget (request and resends) in three layers — the
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
     * S7-05, AC 261 counting: each resend of a sign-in code to an unknown
     * number spends the layers like the request, in the same order; the
     * device budget (5 an hour) lets the request and four resends through,
     * the fifth resend sends nothing — and answers the same.
     */
    public function testAResendToAnUnknownNumberSpendsTheBudgetAndIsSuppressedOnceExhausted(): void
    {
        $context = new OtpRequestContext('203.0.113.77', 'device-1');
        $this->request($context);
        $challengeId = $this->challenges->single()->id()->toString();
        $at          = new \DateTimeImmutable('2026-11-02 09:00:00');

        for ($resend = 1; $resend <= 5; ++$resend) {
            $at     = $at->modify('+31 seconds');
            $issued = $this->resend($challengeId, $context, $at);
            self::assertSame($challengeId, $issued->challengeId);
        }

        self::assertSame(array_merge(
            array_merge(...array_fill(0, 5, ['network:203.0.113.0/24', 'device:device-1', 'global'])),
            ['network:203.0.113.0/24', 'device:device-1'],
        ), $this->portal->budgetCalls, 'every send spends network, device, global in order; the refused device spares the global cap');
        self::assertSame([1, 2, 3, 4, 5], array_map(static fn ($r): int => $r->sendCount, $this->fakes->portalOtps), 'the sixth code is not texted');
        self::assertSame([6, false], [$this->challenges->single()->sendCount(), $this->challenges->single()->isSmsSuppressed()]);
        self::assertSame(195, $this->portal->budget);
    }

    public function testAResendWithoutContextSpendsTheGlobalCapAndAKnownPhoneNothing(): void
    {
        $this->request(null);
        $challengeId          = $this->challenges->single()->id()->toString();
        $this->portal->budget = 0;

        $this->resend($challengeId, null, new \DateTimeImmutable('2026-11-02 09:00:31'));
        self::assertSame([['global', 'global'], 1], [$this->portal->budgetCalls, \count($this->fakes->portalOtps)], 'global cap exhausted: suppressed');

        $this->users->save(User::registerPortal(UserId::fromString('01960000-0000-7000-8000-0000000006e1'), PhoneNumber::fromString(self::PHONE), null, new \DateTimeImmutable('2026-11-01')));
        $this->resend($challengeId, new OtpRequestContext('203.0.113.77'), new \DateTimeImmutable('2026-11-02 09:01:02'));
        self::assertSame([['global', 'global'], 2], [$this->portal->budgetCalls, \count($this->fakes->portalOtps)], 'known: texted without spending');
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

    private function resend(string $challengeId, ?OtpRequestContext $context, \DateTimeImmutable $at): OtpChallengeIssued
    {
        $handler = new ResendPortalOtpHandler($this->challenges, $this->users, $this->portal, $this->portal, $this->fakes, new FrozenClock($at), new UnknownNumberSmsGate($this->users, $this->portal, $this->portal), $this->portal);

        return $handler(new ResendPortalOtp($challengeId, 'sms', $context));
    }

    private function request(?OtpRequestContext $context): void
    {
        $handler = new RequestPortalOtpHandler($this->challenges, $this->portal, new UnknownNumberSmsGate($this->users, $this->portal, $this->portal), $this->portal, $this->portal, $this->fakes, $this->fakes, new FrozenClock(new \DateTimeImmutable('2026-11-02 09:00:00')), $this->portal);

        $handler(new RequestPortalOtp(self::PHONE, 'login', requestContext: $context));
    }
}
