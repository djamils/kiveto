<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Application\Command;

use App\Shared\Domain\ValueObject\PhoneNumber;
use App\Shared\Infrastructure\Security\OpaqueTokenHasher;
use App\System\IdentityAccess\Application\Command\RegisterPortalUser\PortalUserRegistration;
use App\System\IdentityAccess\Application\Command\RegisterPortalUser\RegisterPortalUser;
use App\System\IdentityAccess\Application\Command\RegisterPortalUser\RegisterPortalUserHandler;
use App\System\IdentityAccess\Application\Command\RegisterPortalUser\RegistrationResult;
use App\System\IdentityAccess\Application\Command\RevokeOnboardingGrantsForPhone\RevokeOnboardingGrantsForPhone;
use App\System\IdentityAccess\Application\Command\RevokeOnboardingGrantsForPhone\RevokeOnboardingGrantsForPhoneHandler;
use App\System\IdentityAccess\Application\Service\PortalAccountRegistrar;
use App\System\IdentityAccess\Domain\Exception\PortalAccountExistsException;
use App\System\IdentityAccess\Domain\Otp\OnboardingPurpose;
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
use PHPUnit\Framework\TestCase;

/**
 * S7a (IdentityAccess side), AC 72, 73, 262: a registration grant creates
 * the phone's owner account once, under the per-phone mutex.
 */
final class RegisterPortalUserHandlerTest extends TestCase
{
    private const string PHONE     = '+33612345678';
    private const string CHALLENGE = '01960000-0000-7000-8000-0000000005c1';
    private const string OWNER     = '01960000-0000-7000-8000-0000000005e1';
    private const string TOKEN     = 'registration-secret';

    private InMemoryOtpChallenges $challenges;
    private InMemoryUsers $users;
    private AccountFakes $fakes;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->challenges = new InMemoryOtpChallenges();
        $this->users      = new InMemoryUsers();
        $this->fakes      = new AccountFakes();
        $this->now        = new \DateTimeImmutable('2026-11-02 09:00:00', new \DateTimeZone('UTC'));
    }

    public function testTheGrantCreatesTheOwnerAccountOnce(): void
    {
        $this->grant(OnboardingPurpose::REGISTRATION);

        $registration = $this->register(email: ' Owner@Example.com ');

        self::assertSame(RegistrationResult::REGISTERED, $registration->result);
        $user = $this->users->findById(UserId::fromString((string) $registration->userId));
        self::assertNotNull($user);
        self::assertSame([UserType::PORTAL, UserStatus::ACTIVE, self::PHONE, 'owner@example.com', null], [$user->type(), $user->status(), $user->phone()?->toString(), $user->email(), $user->emailVerifiedAt()]);
        self::assertSame([self::PHONE], $this->users->lockedPhones);
        self::assertSame(['identity-access.portal-user.registered.v1'], $this->fakes->eventNames());
        self::assertSame('09:01:00', $this->challenges->single()->onboardingConsumedAt()?->format('H:i:s'));
        self::assertSame([self::CHALLENGE], $this->challenges->locked);
    }

    /** AC 72: the same token within its 15 minutes answers the same account; then it is invalid. */
    public function testAReplayAnswersTheAccountTheGrantCreatedWhileItLives(): void
    {
        $this->grant(OnboardingPurpose::REGISTRATION);
        $first = $this->register();

        $replay = $this->register(at: '+14 minutes');

        self::assertSame([RegistrationResult::REPLAYED, $first->userId], [$replay->result, $replay->userId]);
        self::assertCount(1, $this->users->users);
        self::assertSame('09:01:00', $this->challenges->single()->onboardingConsumedAt()?->format('H:i:s'), 'consumed once');
        self::assertSame([RegistrationResult::ONBOARDING_INVALID, null], $this->pair($this->register(at: '+15 minutes')));
    }

    public function testAConsumedGrantWhoseAccountIsNoLongerActiveIsInvalid(): void
    {
        $this->grant(OnboardingPurpose::REGISTRATION);
        $user = $this->users->findById(UserId::fromString((string) $this->register()->userId));
        self::assertNotNull($user);
        $user->disablePortal();

        self::assertSame([RegistrationResult::ONBOARDING_INVALID, null], $this->pair($this->register(at: '+2 minutes')));
    }

    /** AC 73: an unknown token or an invitation grant creates nothing. */
    public function testOnlyARegistrationGrantIsAccepted(): void
    {
        self::assertSame([RegistrationResult::ONBOARDING_INVALID, null], $this->pair($this->register()));

        $this->grant(OnboardingPurpose::INVITATION);

        self::assertSame([RegistrationResult::ONBOARDING_INVALID, null], $this->pair($this->register()));
        self::assertSame([[], []], [$this->users->users, $this->users->lockedPhones]);
    }

    /** AC 73: revoking the phone's invitation grants leaves its registration grant usable. */
    public function testRevokingInvitationGrantsLeavesTheRegistrationGrantUsable(): void
    {
        $this->grant(OnboardingPurpose::REGISTRATION);

        self::assertSame(0, (new RevokeOnboardingGrantsForPhoneHandler($this->challenges, new FrozenClock($this->now)))(new RevokeOnboardingGrantsForPhone(self::PHONE)));

        self::assertSame(RegistrationResult::REGISTERED, $this->register()->result);
    }

    /** AC 262: an account created meanwhile (invitation acceptance) wins; the grant stays unused. */
    public function testAnActiveAccountOfThePhoneIsRefusedAndTheGrantStaysUnused(): void
    {
        $this->grant(OnboardingPurpose::REGISTRATION);
        $this->users->save(User::registerPortal(UserId::fromString(self::OWNER), PhoneNumber::fromString(self::PHONE), null, $this->now));

        try {
            $this->register();
            self::fail('The phone already has an account.');
        } catch (PortalAccountExistsException $exception) {
            self::assertSame('Un compte Portal existe déjà pour ce numéro : connectez-vous.', $exception->getMessage());
        }

        self::assertNull($this->challenges->single()->onboardingConsumedAt());
        self::assertSame([self::OWNER], array_keys($this->users->users));
        self::assertSame([self::OWNER], $this->users->locked, 'read past the snapshot, locked');
    }

    public function testADisabledAccountOfThePhoneCreatesNothing(): void
    {
        $this->grant(OnboardingPurpose::REGISTRATION);
        $owner = User::registerPortal(UserId::fromString(self::OWNER), PhoneNumber::fromString(self::PHONE), null, $this->now);
        $owner->disablePortal();
        $this->users->save($owner);

        self::assertSame([RegistrationResult::ACCOUNT_DISABLED, null], $this->pair($this->register()));
        self::assertNull($this->challenges->single()->onboardingConsumedAt());
    }

    public function testAnAddressHeldByAnotherOwnerIsDropped(): void
    {
        $this->grant(OnboardingPurpose::REGISTRATION);
        $this->users->save(User::registerPortal(UserId::fromString(self::OWNER), PhoneNumber::fromString('+33698765432'), 'owner@example.com', $this->now));

        $user = $this->users->findById(UserId::fromString((string) $this->register(email: 'owner@example.com')->userId));

        self::assertNull($user?->email());
    }

    private function grant(OnboardingPurpose $purpose): void
    {
        $hasher    = new OpaqueTokenHasher();
        $challenge = OtpChallenge::start(OtpChallengeId::fromString(self::CHALLENGE), PhoneNumber::fromString(self::PHONE), OtpPurpose::LOGIN, null, hash('sha256', 'code'), $this->now);
        $challenge->verify(hash('sha256', 'code'), $this->now, OtpPurpose::LOGIN);
        $challenge->grantOnboarding($hasher->hash(self::TOKEN), $purpose, $this->now);
        $this->challenges->save($challenge);
    }

    private function register(?string $email = null, string $at = '+1 minute'): PortalUserRegistration
    {
        $handler = new RegisterPortalUserHandler(
            $this->challenges,
            $this->users,
            new OpaqueTokenHasher(),
            new PortalAccountRegistrar($this->users, $this->fakes, $this->fakes->publisher()),
            new FrozenClock($this->now->modify($at)),
        );

        return $handler(new RegisterPortalUser(self::TOKEN, $email));
    }

    /**
     * @return array{RegistrationResult, string|null}
     */
    private function pair(PortalUserRegistration $registration): array
    {
        return [$registration->result, $registration->userId];
    }
}
