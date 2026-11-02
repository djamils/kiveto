<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Application\Command;

use App\Shared\Domain\ValueObject\PhoneNumber;
use App\Shared\Infrastructure\Security\OpaqueTokenHasher;
use App\System\IdentityAccess\Application\Command\RequestPortalOtp\OtpChallengeIssued;
use App\System\IdentityAccess\Application\Command\RequestPortalOtp\RequestPortalOtp;
use App\System\IdentityAccess\Application\Command\RequestPortalOtp\RequestPortalOtpHandler;
use App\System\IdentityAccess\Application\Command\ResendPortalOtp\ResendPortalOtp;
use App\System\IdentityAccess\Application\Command\ResendPortalOtp\ResendPortalOtpHandler;
use App\System\IdentityAccess\Application\Command\VerifyPortalLoginOtp\LoginOtpOutcome;
use App\System\IdentityAccess\Application\Command\VerifyPortalLoginOtp\LoginOtpResult;
use App\System\IdentityAccess\Application\Command\VerifyPortalLoginOtp\VerifyPortalLoginOtp;
use App\System\IdentityAccess\Application\Command\VerifyPortalLoginOtp\VerifyPortalLoginOtpHandler;
use App\System\IdentityAccess\Application\Exception\PhoneNotSupportedException;
use App\System\IdentityAccess\Application\Port\PendingPortalInvitation;
use App\System\IdentityAccess\Application\Port\PortalOtpRequest;
use App\System\IdentityAccess\Application\Query\GetOtpChallengePhone\GetOtpChallengePhone;
use App\System\IdentityAccess\Application\Query\GetOtpChallengePhone\GetOtpChallengePhoneHandler;
use App\System\IdentityAccess\Application\Service\UnknownNumberSmsGate;
use App\System\IdentityAccess\Domain\Exception\OtpChallengeNotFoundException;
use App\System\IdentityAccess\Domain\Exception\OtpRateLimitedException;
use App\System\IdentityAccess\Domain\Otp\OnboardingPurpose;
use App\System\IdentityAccess\Domain\Otp\OtpPurpose;
use App\System\IdentityAccess\Domain\User;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use App\System\IdentityAccess\Domain\ValueObject\UserStatus;
use App\System\IdentityAccess\Domain\ValueObject\UserType;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\System\IdentityAccess\Support\AccountFakes;
use App\Tests\Unit\System\IdentityAccess\Support\InMemoryOtpChallenges;
use App\Tests\Unit\System\IdentityAccess\Support\InMemoryUsers;
use App\Tests\Unit\System\IdentityAccess\Support\PortalFakes;
use PHPUnit\Framework\TestCase;

/**
 * Request, resend and verification of the Portal one-time codes on
 * in-memory doubles (D4, D23, D24).
 */
final class PortalOtpUseCasesTest extends TestCase
{
    private const string PHONE      = '+33612345678';
    private const string OWNER      = '01960000-0000-7000-8000-0000000002e1';
    private const string INVITATION = '01960000-0000-7000-8000-0000000002b1';
    private const string UNKNOWN_ID = '01960000-0000-7000-8000-0000000002ff';

    private InMemoryOtpChallenges $challenges;
    private InMemoryUsers $users;
    private AccountFakes $fakes;
    private PortalFakes $portal;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->challenges = new InMemoryOtpChallenges();
        $this->users      = new InMemoryUsers();
        $this->fakes      = new AccountFakes();
        $this->portal     = new PortalFakes();
        $this->now        = new \DateTimeImmutable('2026-10-26 07:14:00', new \DateTimeZone('UTC'));
    }

    public function testAnUnknownPhoneGetsItsCodeAndSpendsTheBudget(): void
    {
        $this->portal->nextCodes = ['654321'];

        $issued    = $this->request(self::PHONE);
        $challenge = $this->challenges->single();

        self::assertSame([$challenge->id()->toString(), '07:24:00', '07:14:30'], [$issued->challengeId, $issued->expiresAt->format('H:i:s'), $issued->resendAvailableAt->format('H:i:s')]);
        self::assertSame([self::PHONE, OtpPurpose::LOGIN, null, $this->portal->hash('654321'), false], [
            $challenge->phone()->toString(), $challenge->purpose(), $challenge->subjectRef(), $challenge->codeHash(), $challenge->isSmsSuppressed(),
        ]);
        self::assertSame([self::PHONE], $this->portal->policyChecks);
        self::assertSame([1, 199], [$this->portal->budgetConsumptions, $this->portal->budget]);
        self::assertSame([[$challenge->id()->toString(), self::PHONE, '654321', 1, 10]], $this->sent());
    }

    public function testAnActiveOwnerGetsItsCodeWithoutSpendingTheBudget(): void
    {
        $this->users->save($this->owner(UserStatus::ACTIVE));
        $this->portal->budget = 0;

        $this->request(self::PHONE);

        self::assertSame(0, $this->portal->budgetConsumptions);
        self::assertCount(1, $this->fakes->portalOtps);
        self::assertFalse($this->challenges->single()->isSmsSuppressed());
    }

    public function testADisabledOwnerCountsAsAnUnknownNumber(): void
    {
        $this->users->save($this->owner(UserStatus::DISABLED));

        $this->request(self::PHONE);

        self::assertSame(1, $this->portal->budgetConsumptions);
    }

    public function testOnceTheBudgetIsExhaustedTheChallengeExistsButNoSmsLeaves(): void
    {
        $this->portal->budget = 0;

        $issued = $this->request(self::PHONE);

        self::assertSame($issued->challengeId, $this->challenges->single()->id()->toString(), 'the same answer');
        self::assertTrue($this->challenges->single()->isSmsSuppressed());
        self::assertSame([], $this->fakes->portalOtps);
    }

    public function testANumberOutsideThePortalCoverageIsRefusedAndNothingIsStored(): void
    {
        $this->portal->mobile = false;

        try {
            $this->request('+3225551234');
            self::fail('The phone is not supported.');
        } catch (PhoneNotSupportedException $exception) {
            self::assertSame('Ce numéro de mobile n\'est pas encore pris en charge par le Portal.', $exception->getMessage());
        }

        self::assertSame([[], [], 0], [$this->challenges->challenges, $this->fakes->portalOtps, $this->portal->budgetConsumptions]);
    }

    public function testAnInvitationCodeSkipsThePolicyAndTheBudget(): void
    {
        $this->portal->mobile = false;
        $this->portal->budget = 0;

        $this->request(self::PHONE, OtpPurpose::INVITATION, self::INVITATION);

        $challenge = $this->challenges->single();
        self::assertSame([OtpPurpose::INVITATION, self::INVITATION, false], [$challenge->purpose(), $challenge->subjectRef(), $challenge->isSmsSuppressed()]);
        self::assertSame([[], 0], [$this->portal->policyChecks, $this->portal->budgetConsumptions]);
        self::assertCount(1, $this->fakes->portalOtps);
    }

    public function testAResendSendsANewCodeAndTheOldOneStopsWorking(): void
    {
        $this->portal->nextCodes = ['111111', 'refused'];
        $issued                  = $this->request(self::PHONE);

        try {
            $this->resend($issued->challengeId, $this->now->modify('+10 seconds'));
            self::fail('Too early.');
        } catch (OtpRateLimitedException $exception) {
            self::assertSame('07:14:30', $exception->availableAt->format('H:i:s'));
        }

        $this->portal->nextCodes = ['222222'];
        $again                   = $this->resend($issued->challengeId, $this->now->modify('+30 seconds'));

        self::assertSame([$issued->challengeId, '07:24:30', '07:15:00'], [$again->challengeId, $again->expiresAt->format('H:i:s'), $again->resendAvailableAt->format('H:i:s')]);
        self::assertSame([$issued->challengeId, $issued->challengeId], $this->challenges->locked, 'both resends lock the challenge');
        self::assertSame([[$issued->challengeId, self::PHONE, '111111', 1, 10], [$issued->challengeId, self::PHONE, '222222', 2, 10]], $this->sent());

        $this->users->save($this->owner(UserStatus::ACTIVE));
        self::assertSame(LoginOtpResult::OTP_INVALID, $this->verify($issued->challengeId, '111111', '+1 minute')->result);
        self::assertSame(LoginOtpResult::AUTHENTICATED, $this->verify($issued->challengeId, '222222', '+1 minute')->result);
    }

    public function testASuppressedChallengeNeverSendsOnAResendNorSpendsTheBudget(): void
    {
        $this->portal->budget = 0;
        $issued               = $this->request(self::PHONE);

        $this->resend($issued->challengeId, $this->now->modify('+1 minute'));

        self::assertSame([[], 1, 2], [$this->fakes->portalOtps, $this->portal->budgetConsumptions, $this->challenges->single()->sendCount()]);
    }

    public function testAnUnknownMalformedOrUsedChallengeCannotBeResent(): void
    {
        $issued = $this->request(self::PHONE);
        $this->verify($issued->challengeId, '123456', '+5 seconds');

        foreach ([self::UNKNOWN_ID, 'not-a-uuid', $issued->challengeId] as $id) {
            try {
                $this->resend($id, $this->now->modify('+1 minute'));
                self::fail('No challenge to resend: ' . $id);
            } catch (OtpChallengeNotFoundException) {
                $this->addToAssertionCount(1);
            }
        }

        self::assertSame([$issued->challengeId, self::UNKNOWN_ID, $issued->challengeId], $this->challenges->locked, 'a malformed id never reaches the repository');
        self::assertCount(1, $this->fakes->portalOtps);
    }

    public function testTheRightCodeAuthenticatesTheActiveOwner(): void
    {
        $this->users->save($this->owner(UserStatus::ACTIVE));
        $issued = $this->request(self::PHONE);

        $outcome = $this->verify($issued->challengeId, '123456');

        self::assertSame([LoginOtpResult::AUTHENTICATED, self::OWNER], [$outcome->result, $outcome->userId]);
        self::assertSame('07:15:00', $this->challenges->single()->consumedAt()?->format('H:i:s'));
        self::assertSame([$issued->challengeId], $this->challenges->locked);
    }

    public function testTheRightCodeOfAPhoneWithoutAccountAsksForARegistration(): void
    {
        $this->users->save(User::register(UserId::fromString(self::OWNER), 'vet@example.com', 'hash', $this->now, UserType::CLINIC));
        $issued = $this->request(self::PHONE);

        $outcome = $this->verify($issued->challengeId, '123456');

        self::assertSame([LoginOtpResult::REGISTRATION_REQUIRED, null, []], [$outcome->result, $outcome->userId, $outcome->pendingInvitations]);
        self::assertNotNull($outcome->onboardingToken, 'AC 72: a registration token');
        self::assertSame('07:30:00', $outcome->onboardingExpiresAt?->format('H:i:s'), '15 minutes after the verification');
        $challenge = $this->challenges->single();
        self::assertSame([hash('sha256', $outcome->onboardingToken), OnboardingPurpose::REGISTRATION, null], [$challenge->onboardingTokenHash(), $challenge->onboardingPurpose(), $challenge->onboardingConsumedAt()]);
    }

    public function testTheRightCodeOfADisabledOwnerOpensNothing(): void
    {
        $this->users->save($this->owner(UserStatus::DISABLED));
        $issued = $this->request(self::PHONE);

        self::assertSame([LoginOtpResult::ACCOUNT_DISABLED, null], $this->pair($this->verify($issued->challengeId, '123456')));
    }

    public function testEveryFailureIsAnOutcomeAndItsAttemptIsSaved(): void
    {
        $this->users->save($this->owner(UserStatus::ACTIVE));
        $issued = $this->request(self::PHONE);
        $saves  = $this->challenges->saves;

        self::assertSame(LoginOtpResult::OTP_INVALID, $this->verify(self::UNKNOWN_ID, '123456')->result);
        self::assertSame(LoginOtpResult::OTP_INVALID, $this->verify('not-a-uuid', '123456')->result);
        self::assertSame($saves, $this->challenges->saves, 'nothing to save without a challenge');

        for ($i = 0; $i < 4; ++$i) {
            self::assertSame(LoginOtpResult::OTP_INVALID, $this->verify($issued->challengeId, '000000')->result);
        }

        self::assertSame(LoginOtpResult::OTP_ATTEMPTS_EXCEEDED, $this->verify($issued->challengeId, '000000')->result);
        self::assertSame(LoginOtpResult::OTP_ATTEMPTS_EXCEEDED, $this->verify($issued->challengeId, '123456')->result);
        self::assertSame([5, $saves + 6], [$this->challenges->single()->attempts(), $this->challenges->saves]);
    }

    public function testAnExpiredOrAlreadyUsedLoginCodeIsRefused(): void
    {
        $this->users->save($this->owner(UserStatus::ACTIVE));
        $expired = $this->request(self::PHONE);
        self::assertSame(LoginOtpResult::OTP_EXPIRED, $this->verify($expired->challengeId, '123456', '+10 minutes')->result);

        $this->challenges->challenges = [];
        $used                         = $this->request(self::PHONE);
        self::assertSame(LoginOtpResult::AUTHENTICATED, $this->verify($used->challengeId, '123456')->result);
        self::assertSame([LoginOtpResult::OTP_INVALID, null], $this->pair($this->verify($used->challengeId, '123456', '+2 minutes')), 'a replay is invalid on the login route');
    }

    public function testAnInvitationCodeDoesNotOpenASession(): void
    {
        $this->users->save($this->owner(UserStatus::ACTIVE));
        $issued = $this->request(self::PHONE, OtpPurpose::INVITATION, self::INVITATION);

        self::assertSame(LoginOtpResult::OTP_INVALID, $this->verify($issued->challengeId, '123456')->result);
        self::assertSame(1, $this->challenges->single()->attempts());
    }

    /** F-G: a phone a clinic invited counts as known — no budget spent. */
    public function testAnInvitedPhoneDoesNotSpendTheBudget(): void
    {
        $this->portal->pending[self::PHONE] = [new PendingPortalInvitation(self::INVITATION, 'Clinique du Parc', 'Lyon')];
        $this->portal->budget               = 0;

        $this->request(self::PHONE);

        self::assertSame(0, $this->portal->budgetConsumptions);
        self::assertFalse($this->challenges->single()->isSmsSuppressed());
        self::assertCount(1, $this->fakes->portalOtps);
    }

    /** D29: a phone without account but with invitations gets a grant, minted once. */
    public function testAnInvitedPhoneWithoutAccountGetsAnOnboardingGrant(): void
    {
        $this->portal->pending[self::PHONE] = [new PendingPortalInvitation(self::INVITATION, 'Clinique du Parc', 'Lyon')];
        $issued                             = $this->request(self::PHONE);

        $outcome = $this->verify($issued->challengeId, '123456');

        self::assertSame([LoginOtpResult::INVITATION_PENDING, null], $this->pair($outcome));
        self::assertNotNull($outcome->onboardingToken);
        self::assertSame('07:30:00', $outcome->onboardingExpiresAt?->format('H:i:s'), '15 minutes after the verification');
        self::assertSame(self::INVITATION, $outcome->pendingInvitations[0]->invitationId ?? null);
        $challenge = $this->challenges->single();
        self::assertSame(hash('sha256', $outcome->onboardingToken), $challenge->onboardingTokenHash(), 'only the digest is kept');
        self::assertSame(OnboardingPurpose::INVITATION, $challenge->onboardingPurpose());
        self::assertSame(LoginOtpResult::OTP_INVALID, $this->verify($issued->challengeId, '123456', '+2 minutes')->result, 'no second grant');
    }

    public function testAnActiveOwnerWithInvitationsIsAuthenticatedWithAGrant(): void
    {
        $this->users->save($this->owner(UserStatus::ACTIVE));
        $this->portal->pending[self::PHONE] = [new PendingPortalInvitation(self::INVITATION, 'Clinique du Parc', null)];
        $issued                             = $this->request(self::PHONE);

        $outcome = $this->verify($issued->challengeId, '123456');

        self::assertSame([LoginOtpResult::AUTHENTICATED, self::OWNER], $this->pair($outcome));
        self::assertNotNull($outcome->onboardingToken);
        self::assertCount(1, $outcome->pendingInvitations);
    }

    /** AC 76: a disabled owner gets neither session nor grant. */
    public function testADisabledOwnerGetsNoGrant(): void
    {
        $this->users->save($this->owner(UserStatus::DISABLED));
        $this->portal->pending[self::PHONE] = [new PendingPortalInvitation(self::INVITATION, 'Clinique du Parc', null)];
        $issued                             = $this->request(self::PHONE);

        $outcome = $this->verify($issued->challengeId, '123456');

        self::assertSame([LoginOtpResult::ACCOUNT_DISABLED, null, null], [$outcome->result, $outcome->userId, $outcome->onboardingToken]);
        self::assertNull($this->challenges->single()->onboardingTokenHash());
    }

    public function testThePhoneOfAChallengeIsReadWithoutLock(): void
    {
        $issued  = $this->request(self::PHONE);
        $handler = new GetOtpChallengePhoneHandler($this->challenges);

        self::assertSame(self::PHONE, $handler(new GetOtpChallengePhone($issued->challengeId)));
        self::assertNull($handler(new GetOtpChallengePhone(self::UNKNOWN_ID)));
        self::assertNull($handler(new GetOtpChallengePhone('not-a-uuid')));
        self::assertSame([], $this->challenges->locked);
    }

    private function request(string $phone, OtpPurpose $purpose = OtpPurpose::LOGIN, ?string $invitationRef = null): OtpChallengeIssued
    {
        $handler = new RequestPortalOtpHandler($this->challenges, $this->portal, new UnknownNumberSmsGate($this->users, $this->portal, $this->portal), $this->portal, $this->portal, $this->fakes, $this->fakes, new FrozenClock($this->now));

        return $handler(new RequestPortalOtp($phone, $purpose->value, $invitationRef));
    }

    private function resend(string $challengeId, \DateTimeImmutable $at): OtpChallengeIssued
    {
        return (new ResendPortalOtpHandler($this->challenges, $this->users, $this->portal, $this->portal, $this->fakes, new FrozenClock($at), new UnknownNumberSmsGate($this->users, $this->portal, $this->portal)))(new ResendPortalOtp($challengeId, 'sms'));
    }

    private function verify(string $challengeId, string $code, string $after = '+1 minute'): LoginOtpOutcome
    {
        return (new VerifyPortalLoginOtpHandler($this->challenges, $this->users, $this->portal, new OpaqueTokenHasher(), $this->portal, new FrozenClock($this->now->modify($after))))(new VerifyPortalLoginOtp($challengeId, $code));
    }

    /**
     * @return array{LoginOtpResult, string|null}
     */
    private function pair(LoginOtpOutcome $outcome): array
    {
        return [$outcome->result, $outcome->userId];
    }

    /**
     * @return list<array{string, string, string, int, int}>
     */
    private function sent(): array
    {
        return array_map(static fn (PortalOtpRequest $r): array => [$r->challengeId, $r->phone, $r->code, $r->sendCount, $r->validityMinutes], $this->fakes->portalOtps);
    }

    private function owner(UserStatus $status): User
    {
        return User::reconstitute(UserId::fromString(self::OWNER), null, 'hash', $this->now, $status, null, UserType::PORTAL, PhoneNumber::fromString(self::PHONE), $this->now);
    }
}
