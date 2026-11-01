<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Domain\Otp;

use App\Shared\Domain\ValueObject\PhoneNumber;
use App\System\IdentityAccess\Domain\Otp\OnboardingPurpose;
use App\System\IdentityAccess\Domain\Otp\OtpChallenge;
use App\System\IdentityAccess\Domain\Otp\OtpChallengeId;
use App\System\IdentityAccess\Domain\Otp\OtpChannel;
use App\System\IdentityAccess\Domain\Otp\OtpPurpose;
use PHPUnit\Framework\TestCase;

/**
 * S7a: an onboarding grant carries its purpose; a registration grant is
 * consumed once, while it lives.
 */
final class OtpChallengeOnboardingPurposeTest extends TestCase
{
    private const string ID = '01960000-0000-7000-8000-0000000004a1';

    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-11-02 09:00:00', new \DateTimeZone('UTC'));
    }

    public function testAGrantRecordsItsPurpose(): void
    {
        self::assertNull($this->login()->onboardingPurpose(), 'no grant, no purpose');

        self::assertSame(OnboardingPurpose::INVITATION, $this->granted(OnboardingPurpose::INVITATION)->onboardingPurpose());
        $registration = $this->granted(OnboardingPurpose::REGISTRATION);
        self::assertSame([OnboardingPurpose::REGISTRATION, null, '09:15:00'], [$registration->onboardingPurpose(), $registration->onboardingConsumedAt(), $registration->onboardingExpiresAt()?->format('H:i:s')]);
    }

    public function testARegistrationGrantIsConsumedOnce(): void
    {
        $challenge = $this->granted(OnboardingPurpose::REGISTRATION);

        $challenge->consumeRegistrationGrant($this->now->modify('+2 minutes'));

        self::assertSame('09:02:00', $challenge->onboardingConsumedAt()?->format('H:i:s'));
        self::assertTrue($challenge->isOnboardingUsable($this->now->modify('+3 minutes')), 'still alive: a replay is honoured by the caller');
        $this->assertConsumptionRefused($challenge, $this->now->modify('+3 minutes'), 'a second consumption');
    }

    public function testOnlyALiveRegistrationGrantIsConsumed(): void
    {
        $this->assertConsumptionRefused($this->login(), $this->now, 'no grant');
        $this->assertConsumptionRefused($this->granted(OnboardingPurpose::INVITATION), $this->now, 'an invitation grant');
        $this->assertConsumptionRefused($this->granted(OnboardingPurpose::REGISTRATION), $this->now->modify('+15 minutes'), 'an expired grant');

        $revoked = $this->granted(OnboardingPurpose::REGISTRATION);
        $revoked->revokeOnboarding($this->now);
        $this->assertConsumptionRefused($revoked, $this->now->modify('+1 minute'), 'a revoked grant');
    }

    public function testAReloadedChallengeKeepsItsPurposeAndConsumption(): void
    {
        $challenge = OtpChallenge::reconstitute(
            OtpChallengeId::fromString(self::ID),
            PhoneNumber::fromString('+33612345678'),
            OtpPurpose::LOGIN,
            null,
            hash('sha256', 'code'),
            0,
            1,
            $this->now->modify('+10 minutes'),
            $this->now->modify('+30 seconds'),
            OtpChannel::SMS,
            false,
            $this->now,
            hash('sha256', 'grant'),
            $this->now->modify('+15 minutes'),
            null,
            $this->now,
            onboardingPurpose: OnboardingPurpose::REGISTRATION,
            onboardingConsumedAt: $this->now->modify('+1 minute'),
        );

        self::assertSame([OnboardingPurpose::REGISTRATION, '09:01:00'], [$challenge->onboardingPurpose(), $challenge->onboardingConsumedAt()?->format('H:i:s')]);
        $this->assertConsumptionRefused($challenge, $this->now->modify('+2 minutes'), 'already consumed');
    }

    private function login(): OtpChallenge
    {
        return OtpChallenge::start(OtpChallengeId::fromString(self::ID), PhoneNumber::fromString('+33612345678'), OtpPurpose::LOGIN, null, hash('sha256', 'code'), $this->now);
    }

    private function granted(OnboardingPurpose $purpose): OtpChallenge
    {
        $challenge = $this->login();
        $challenge->verify(hash('sha256', 'code'), $this->now, OtpPurpose::LOGIN);
        $challenge->grantOnboarding(hash('sha256', 'grant'), $purpose, $this->now);

        return $challenge;
    }

    private function assertConsumptionRefused(OtpChallenge $challenge, \DateTimeImmutable $at, string $message): void
    {
        try {
            $challenge->consumeRegistrationGrant($at);
            self::fail('Consumption should be refused: ' . $message);
        } catch (\LogicException $exception) {
            self::assertSame('Only a live, unused registration grant can be consumed.', $exception->getMessage(), $message);
        }
    }
}
