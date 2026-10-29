<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Application\Command\ClaimPortalIdentity;

use App\Shared\Domain\ValueObject\PhoneNumber;
use App\Shared\Infrastructure\Security\OpaqueTokenHasher;
use App\System\IdentityAccess\Application\Command\ClaimPortalIdentity\ClaimOutcome;
use App\System\IdentityAccess\Application\Command\ClaimPortalIdentity\ClaimPortalIdentity;
use App\System\IdentityAccess\Application\Command\ClaimPortalIdentity\ClaimPortalIdentityHandler;
use App\System\IdentityAccess\Application\Command\ClaimPortalIdentity\ClaimResult;
use App\System\IdentityAccess\Application\Command\RevokeOnboardingGrantsForPhone\RevokeOnboardingGrantsForPhone;
use App\System\IdentityAccess\Application\Command\RevokeOnboardingGrantsForPhone\RevokeOnboardingGrantsForPhoneHandler;
use App\System\IdentityAccess\Domain\Otp\OtpChallenge;
use App\System\IdentityAccess\Domain\Otp\OtpChallengeId;
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
 * D7, D29 (IdentityAccess side): the proof is verified here, then the
 * phone's owner account is found or created under the per-phone mutex.
 */
final class ClaimPortalIdentityHandlerTest extends TestCase
{
    private const string PHONE      = '+33612345678';
    private const string OTHER      = '+33698765432';
    private const string INVITATION = '01960000-0000-7000-8000-0000000003b1';
    private const string CHALLENGE  = '01960000-0000-7000-8000-0000000003c1';
    private const string OWNER      = '01960000-0000-7000-8000-0000000003e1';
    private const string GRANT      = 'grant-secret';

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
        $this->now        = new \DateTimeImmutable('2026-10-29 10:00:00', new \DateTimeZone('UTC'));
    }

    public function testAProvenPhoneWithoutAccountGetsANewOwnerAccount(): void
    {
        $this->invitationChallenge();

        $outcome = $this->claimWithCode('123456', email: ' Famille@Example.com ');

        self::assertSame(ClaimResult::VERIFIED, $outcome->result);
        $user = $this->users->findById(UserId::fromString((string) $outcome->userId));
        self::assertNotNull($user);
        self::assertSame([UserType::PORTAL, UserStatus::ACTIVE, self::PHONE, 'famille@example.com'], [$user->type(), $user->status(), $user->phone()?->toString(), $user->email()]);
        self::assertSame([self::PHONE], $this->users->lockedPhones);
        self::assertSame(['identity-access.portal-user.registered.v1'], $this->fakes->eventNames());
        self::assertNotNull($this->challenges->single()->consumedAt(), 'the code is used');
    }

    /** AC 54: family members sharing one address — the second account goes without. */
    public function testAnAddressHeldByAnotherOwnerIsNotCopied(): void
    {
        $this->users->save(User::registerPortal(UserId::fromString(self::OWNER), PhoneNumber::fromString(self::OTHER), 'famille@example.com', $this->now));
        $this->invitationChallenge();

        $outcome = $this->claimWithCode('123456', email: 'famille@example.com');

        self::assertNull($this->users->findById(UserId::fromString((string) $outcome->userId))?->email());
    }

    /** AC 55: an address taken by another phone between lookup and insert. */
    public function testAnAddressTakenMeanwhileCostsTheNewAccountItsEmail(): void
    {
        $this->users->committedMeanwhile = User::registerPortal(UserId::fromString(self::OWNER), PhoneNumber::fromString(self::OTHER), 'famille@example.com', $this->now);
        $this->invitationChallenge();

        $outcome = $this->claimWithCode('123456', email: 'famille@example.com');

        $user = $this->users->findById(UserId::fromString((string) $outcome->userId));
        self::assertSame([self::PHONE, null], [$user?->phone()?->toString(), $user?->email()]);
        self::assertCount(2, $this->users->users);
    }

    public function testAPhoneTakenDespiteTheMutexIsALogicError(): void
    {
        $this->users->committedMeanwhile = User::registerPortal(UserId::fromString(self::OWNER), PhoneNumber::fromString(self::PHONE), null, $this->now);
        $this->invitationChallenge();

        $this->expectException(\LogicException::class);
        $this->claimWithCode('123456');
    }

    public function testAnActiveOwnerIsAttachedAndADisabledOneRefused(): void
    {
        $this->users->save($this->owner(UserStatus::ACTIVE));
        $this->invitationChallenge();
        self::assertSame([ClaimResult::VERIFIED, self::OWNER], $this->pair($this->claimWithCode('123456')));
        self::assertSame([], $this->fakes->eventNames(), 'no new account');

        $this->users->save($this->owner(UserStatus::DISABLED));
        self::assertSame([ClaimResult::ACCOUNT_DISABLED, null], $this->pair($this->claimWithCode('123456')));
    }

    /** The retry check never creates anything. */
    public function testWithoutCreationOnlyAnExistingAccountIsAnswered(): void
    {
        $this->invitationChallenge();

        self::assertSame([ClaimResult::VERIFIED, null], $this->pair($this->claimWithCode('123456', create: false)));
        self::assertSame([], $this->users->users);
    }

    /** D4 replay: the same code again, before its original expiry. */
    public function testAUsedInvitationCodeIsARetryUntilItsExpiry(): void
    {
        $this->users->save($this->owner(UserStatus::ACTIVE));
        $this->invitationChallenge();
        $this->claimWithCode('123456');

        self::assertSame([ClaimResult::ALREADY_VERIFIED, self::OWNER], $this->pair($this->claimWithCode('123456', create: false)));
        self::assertSame([ClaimResult::OTP_INVALID, null], $this->pair($this->claimWithCode('654321', create: false)));
        self::assertSame(1, $this->challenges->single()->attempts(), 'wrong guesses on a used code still count');

        for ($attempt = 2; $attempt <= 5; ++$attempt) {
            $this->claimWithCode('654321', create: false);
        }
        self::assertSame(ClaimResult::OTP_INVALID, $this->claimWithCode('123456', create: false)->result, 'no replay once the attempts are spent');
    }

    /** An address longer than the account column is not copied. */
    public function testAnOverlongAddressIsNotCopied(): void
    {
        $this->invitationChallenge();

        $outcome = $this->claimWithCode('123456', email: str_repeat('a', 170) . '@example.com');

        self::assertNull($this->users->findById(UserId::fromString((string) $outcome->userId))?->email());
    }

    public function testWrongProofsAreCommittedOutcomes(): void
    {
        $this->invitationChallenge();
        self::assertSame(ClaimResult::OTP_INVALID, $this->claimWithCode('000000')->result);
        self::assertSame(1, $this->challenges->single()->attempts());

        for ($attempt = 2; $attempt <= 4; ++$attempt) {
            $this->claimWithCode('000000');
        }
        self::assertSame(ClaimResult::OTP_ATTEMPTS_EXCEEDED, $this->claimWithCode('000000')->result);

        $this->challenges->challenges = [];
        $this->invitationChallenge();
        self::assertSame(ClaimResult::OTP_EXPIRED, $this->claimWithCode('123456', at: '+10 minutes')->result);

        self::assertSame(ClaimResult::OTP_INVALID, $this->claim(new ClaimPortalIdentity(self::INVITATION, self::PHONE, '01960000-0000-7000-8000-0000000003ff', '123456'))->result);
        self::assertSame(ClaimResult::OTP_INVALID, $this->claim(new ClaimPortalIdentity(self::INVITATION, self::PHONE, 'not-a-uuid', '123456'))->result);
        self::assertSame([], $this->users->lockedPhones, 'no proof, no lock, no account');
    }

    /** AC 27: a login code or another invitation's code does not prove this invitation. */
    public function testACodeOfAnotherPurposeIsInvalid(): void
    {
        $this->challenges->save(OtpChallenge::start(OtpChallengeId::fromString(self::CHALLENGE), PhoneNumber::fromString(self::PHONE), OtpPurpose::LOGIN, null, $this->portal->hash('123456'), $this->now));

        self::assertSame(ClaimResult::OTP_INVALID, $this->claimWithCode('123456')->result);
        self::assertSame(1, $this->challenges->single()->attempts());
    }

    public function testAnInvitationCodeOfAnotherPhoneIsAMismatch(): void
    {
        $this->invitationChallenge(self::OTHER);

        self::assertSame(ClaimResult::PHONE_MISMATCH, $this->claimWithCode('123456')->result);
    }

    public function testAnOnboardingGrantProvesItsOwnPhoneOnly(): void
    {
        $this->grantChallenge(self::PHONE);

        self::assertSame(ClaimResult::VERIFIED, $this->claimWithGrant(self::GRANT, self::PHONE)->result);
        self::assertSame(ClaimResult::PHONE_MISMATCH, $this->claimWithGrant(self::GRANT, self::OTHER)->result, 'AC 68');
        self::assertSame(ClaimResult::ONBOARDING_INVALID, $this->claimWithGrant('unknown', self::PHONE)->result);
        self::assertSame(ClaimResult::ONBOARDING_INVALID, $this->claimWithGrant(self::GRANT, self::PHONE, '+15 minutes')->result, 'AC 57: expired');

        self::assertSame(1, new RevokeOnboardingGrantsForPhoneHandler($this->challenges, new FrozenClock($this->now))(new RevokeOnboardingGrantsForPhone(self::PHONE)));
        self::assertSame(ClaimResult::ONBOARDING_INVALID, $this->claimWithGrant(self::GRANT, self::PHONE)->result, 'AC 70: revoked');
    }

    public function testAClaimNeedsAProof(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->claim(new ClaimPortalIdentity(self::INVITATION, self::PHONE));
    }

    private function invitationChallenge(string $phone = self::PHONE): void
    {
        $this->challenges->save(OtpChallenge::start(OtpChallengeId::fromString(self::CHALLENGE), PhoneNumber::fromString($phone), OtpPurpose::INVITATION, self::INVITATION, $this->portal->hash('123456'), $this->now));
    }

    private function grantChallenge(string $phone): void
    {
        $challenge = OtpChallenge::start(OtpChallengeId::fromString(self::CHALLENGE), PhoneNumber::fromString($phone), OtpPurpose::LOGIN, null, $this->portal->hash('123456'), $this->now);
        $challenge->verify($this->portal->hash('123456'), $this->now, OtpPurpose::LOGIN);
        $challenge->grantOnboarding(new OpaqueTokenHasher()->hash(self::GRANT), $this->now);
        $this->challenges->save($challenge);
    }

    private function claimWithCode(string $code, ?string $email = null, bool $create = true, string $at = '+1 minute'): ClaimOutcome
    {
        return $this->claim(new ClaimPortalIdentity(self::INVITATION, self::PHONE, self::CHALLENGE, $code, null, $email, $create), $at);
    }

    private function claimWithGrant(string $token, string $phone, string $at = '+1 minute'): ClaimOutcome
    {
        return $this->claim(new ClaimPortalIdentity(self::INVITATION, $phone, onboardingToken: $token), $at);
    }

    private function claim(ClaimPortalIdentity $command, string $at = '+1 minute'): ClaimOutcome
    {
        $handler = new ClaimPortalIdentityHandler($this->challenges, $this->users, $this->portal, new OpaqueTokenHasher(), $this->fakes, new FrozenClock($this->now->modify($at)), $this->fakes->publisher());

        return $handler($command);
    }

    /**
     * @return array{ClaimResult, string|null}
     */
    private function pair(ClaimOutcome $outcome): array
    {
        return [$outcome->result, $outcome->userId];
    }

    private function owner(UserStatus $status): User
    {
        return User::reconstitute(UserId::fromString(self::OWNER), null, '!h', $this->now, $status, null, UserType::PORTAL, PhoneNumber::fromString(self::PHONE), $this->now);
    }
}
