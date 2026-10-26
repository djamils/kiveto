<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Application\Command;

use App\System\IdentityAccess\Application\Command\ActivateInvitedUser\ActivateInvitedUser;
use App\System\IdentityAccess\Application\Command\ActivateInvitedUser\ActivateInvitedUserHandler;
use App\System\IdentityAccess\Application\Command\ActivateInvitedUser\ActivationResult;
use App\System\IdentityAccess\Application\Command\RegisterInvitedUser\RegisterInvitedUser;
use App\System\IdentityAccess\Application\Command\RegisterInvitedUser\RegisterInvitedUserHandler;
use App\System\IdentityAccess\Application\Command\RequestPasswordReset\RequestPasswordReset;
use App\System\IdentityAccess\Application\Command\RequestPasswordReset\RequestPasswordResetHandler;
use App\System\IdentityAccess\Application\Command\ResetPassword\ResetPassword;
use App\System\IdentityAccess\Application\Command\ResetPassword\ResetPasswordHandler;
use App\System\IdentityAccess\Application\Command\SendInvitation\SendInvitation;
use App\System\IdentityAccess\Application\Command\SendInvitation\SendInvitationHandler;
use App\System\IdentityAccess\Application\Exception\InvalidAccountTokenException;
use App\System\IdentityAccess\Application\Exception\UnacceptablePasswordException;
use App\System\IdentityAccess\Domain\Exception\AccountDisabledException;
use App\System\IdentityAccess\Domain\Exception\EmailUsedByAnotherAccountException;
use App\System\IdentityAccess\Domain\Token\AccountToken;
use App\System\IdentityAccess\Domain\Token\TokenPurpose;
use App\System\IdentityAccess\Domain\User;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use App\System\IdentityAccess\Domain\ValueObject\UserStatus;
use App\System\IdentityAccess\Domain\ValueObject\UserType;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\System\IdentityAccess\Support\AccountFakes;
use App\Tests\Unit\System\IdentityAccess\Support\InMemoryAccountTokens;
use App\Tests\Unit\System\IdentityAccess\Support\InMemoryUsers;
use PHPUnit\Framework\TestCase;

/**
 * Invitation, activation and reset on in-memory doubles (D11).
 */
final class AccountUseCasesTest extends TestCase
{
    private const string CLINIC_A = '01960000-0000-7000-8000-0000000000c1';
    private const string CLINIC_B = '01960000-0000-7000-8000-0000000000c2';

    private InMemoryUsers $users;
    private InMemoryAccountTokens $tokens;
    private AccountFakes $fakes;
    private FrozenClock $clock;

    protected function setUp(): void
    {
        $this->users  = new InMemoryUsers();
        $this->tokens = new InMemoryAccountTokens();
        $this->fakes  = new AccountFakes();
        $this->clock  = new FrozenClock(new \DateTimeImmutable('2026-10-26 07:14:00', new \DateTimeZone('UTC')));
    }

    public function testAnUnknownAddressBecomesAPendingClinicAccount(): void
    {
        $registration = $this->register(' Hugo.Martin@Example.com ');

        $user = $this->users->findById(UserId::fromString($registration->userId));
        self::assertNotNull($user);
        self::assertFalse($registration->alreadyActive);
        self::assertSame(['hugo.martin@example.com', UserStatus::PENDING, UserType::CLINIC], [$user->email(), $user->status(), $user->type()]);
        self::assertSame(['identity-access.user.invited.v1'], $this->fakes->eventNames());

        $again = $this->register('hugo.martin@example.com');
        self::assertSame([$registration->userId, false], [$again->userId, $again->alreadyActive], 'a pending account is reused');
    }

    public function testAnActiveClinicAccountJoinsAndOtherAccountsAreRefused(): void
    {
        $active       = $this->existing('claire@example.com', UserStatus::ACTIVE, UserType::CLINIC);
        $registration = $this->register('claire@example.com');
        self::assertSame([$active->id()->toString(), true], [$registration->userId, $registration->alreadyActive]);

        $this->existing('owner@example.com', UserStatus::ACTIVE, UserType::PORTAL);
        try {
            $this->register('owner@example.com');
            self::fail('A portal address was expected to be refused.');
        } catch (EmailUsedByAnotherAccountException $exception) {
            self::assertSame('Cette adresse est déjà utilisée par un autre compte Kiveto.', $exception->getMessage());
        }

        $this->existing('gone@example.com', UserStatus::DISABLED, UserType::CLINIC);
        $this->expectException(AccountDisabledException::class);
        $this->expectExceptionMessage('Ce compte est désactivé. Contactez le support Kiveto.');
        $this->register('gone@example.com');
    }

    /**
     * AC 28 / 29: a 72-hour link; « Renvoyer » replaces the link of that clinic only.
     */
    public function testAnInvitationIsALinkOfTheClinic(): void
    {
        $userId = $this->register('hugo@example.com')->userId;

        $first = $this->invite($userId, self::CLINIC_A);
        self::assertNotNull($first);
        self::assertCount(1, $this->fakes->invitations);
        $request = $this->fakes->invitations[0];
        self::assertSame([$first, $userId, 'hugo@example.com', self::CLINIC_A, 'Hugo', 'Dr Claire Lambert', 'Clinique Montmartre', 'Paris', 'Vétérinaire'], [
            $request->tokenId, $request->userId, $request->email, $request->clinicId, $request->firstName, $request->inviterName, $request->clinicName, $request->clinicCity, $request->roleLabel,
        ]);
        $token = $this->tokens->findByHash(AccountToken::hash(AccountFakes::secretOf($request->activationUrl)));
        self::assertNotNull($token);
        self::assertSame(['2026-10-29 07:14', self::CLINIC_A], [$token->expiresAt()->format('Y-m-d H:i'), $token->clinicId()]);

        $this->invite($userId, self::CLINIC_B);
        $this->invite($userId, self::CLINIC_A);

        $invitations = $this->tokens->of($userId, TokenPurpose::INVITATION);
        self::assertSame([false, true, true], array_map(fn (AccountToken $t): bool => $t->isUsable($this->clock->now()), $invitations), "A's first link is replaced, B's stays");
    }

    public function testNoInvitationIsSentToAnAccountThatIsNotPending(): void
    {
        $active = $this->existing('claire@example.com', UserStatus::ACTIVE, UserType::CLINIC);

        self::assertNull($this->invite($active->id()->toString(), self::CLINIC_A));
        self::assertSame([], $this->fakes->invitations);

        $this->expectException(\DomainException::class);
        $this->invite('01960000-0000-7000-8000-0000000000ff', self::CLINIC_A);
    }

    /**
     * AC 29 / 29b: activation through A's link; B's link then says « déjà actif ».
     */
    public function testTheInvitedPersonChoosesTheirPassword(): void
    {
        $userId = $this->register('hugo@example.com')->userId;
        $this->invite($userId, self::CLINIC_A);
        $this->invite($userId, self::CLINIC_B);
        [$linkA, $linkB]     = array_map(static fn ($r): string => AccountFakes::secretOf($r->activationUrl), $this->fakes->invitations);
        $this->fakes->events = [];

        try {
            $this->activate($linkA, 'court');
            self::fail('A short password was expected to be refused.');
        } catch (UnacceptablePasswordException $exception) {
            self::assertSame('Choisissez un mot de passe d’au moins 8 caractères.', $exception->getMessage());
        }

        self::assertSame(ActivationResult::ACTIVATED, $this->activate($linkA, 'un-mot-de-passe'));
        $user = $this->users->findById(UserId::fromString($userId));
        self::assertSame([UserStatus::ACTIVE, 'hashed:un-mot-de-passe'], [$user?->status(), $user?->passwordHash()]);
        self::assertSame(['identity-access.user.activated.v1'], $this->fakes->eventNames());

        self::assertSame(ActivationResult::ALREADY_ACTIVE, $this->activate($linkB, 'whatever'));
        self::assertNotNull($this->tokens->findByHash(AccountToken::hash($linkB))?->usedAt(), "B's link is spent");

        $this->expectException(InvalidAccountTokenException::class);
        $this->activate($linkA, 'un-autre-mot');
    }

    public function testAnUnusableInvitationLinkIsRefused(): void
    {
        $userId = $this->register('hugo@example.com')->userId;
        $this->invite($userId, self::CLINIC_A);
        $link = AccountFakes::secretOf($this->fakes->invitations[0]->activationUrl);

        foreach (['unknown', $link] as $i => $secret) {
            if (1 === $i) {
                $this->clock = new FrozenClock(new \DateTimeImmutable('2026-10-29 08:00:00', new \DateTimeZone('UTC')));
            }

            try {
                $this->activate($secret, 'un-mot-de-passe');
                self::fail('The link was expected to be refused.');
            } catch (InvalidAccountTokenException $exception) {
                self::assertSame('Ce lien n’est plus valide.', $exception->getMessage());
            }
        }
    }

    public function testADisabledOrVanishedAccountCannotBeActivated(): void
    {
        $userId = $this->register('hugo@example.com')->userId;
        $this->invite($userId, self::CLINIC_A);
        $this->invite($this->register('lea@example.com')->userId, self::CLINIC_A);
        [$hugo, $lea] = array_map(static fn ($r): string => AccountFakes::secretOf($r->activationUrl), $this->fakes->invitations);

        $this->users->save(User::reconstitute(UserId::fromString($userId), 'hugo@example.com', '!', new \DateTimeImmutable(), UserStatus::DISABLED, null, UserType::CLINIC));
        $this->users->users = array_filter($this->users->users, static fn (User $u): bool => 'lea@example.com' !== $u->email());

        $refused = 0;
        foreach ([$hugo, $lea] as $secret) {
            try {
                $this->activate($secret, 'un-mot-de-passe');
            } catch (InvalidAccountTokenException) {
                ++$refused;
            }
        }

        self::assertSame(2, $refused);
    }

    /**
     * AC 31: only an ACTIVE clinic account gets a 30-minute link.
     */
    public function testOnlyAnActiveClinicAccountReceivesAResetLink(): void
    {
        $this->existing('claire@example.com', UserStatus::ACTIVE, UserType::CLINIC);
        $this->existing('owner@example.com', UserStatus::ACTIVE, UserType::PORTAL);
        $this->existing('gone@example.com', UserStatus::DISABLED, UserType::CLINIC);
        $this->register('hugo@example.com');

        foreach (['unknown@example.com', 'owner@example.com', 'gone@example.com', 'hugo@example.com', ' Claire@Example.com'] as $email) {
            $this->requestReset($email);
        }

        self::assertCount(1, $this->fakes->resets);
        $request = $this->fakes->resets[0];
        self::assertSame(['claire@example.com', '26/10/2026 à 08:14'], [$request->email, $request->requestedAtLabel]);
        $token = $this->tokens->findByHash(AccountToken::hash(AccountFakes::secretOf($request->resetUrl)));
        self::assertNotNull($token);
        self::assertSame(['2026-10-26 07:44', null], [$token->expiresAt()->format('Y-m-d H:i'), $token->clinicId()]);

        $this->requestReset('claire@example.com');
        self::assertFalse($token->isUsable($this->clock->now()), 'a new request replaces the previous link');
    }

    public function testAResetChangesThePasswordOnceAndSpendsEveryLink(): void
    {
        $user = $this->existing('claire@example.com', UserStatus::ACTIVE, UserType::CLINIC);
        $this->requestReset('claire@example.com');
        $this->requestReset('claire@example.com');
        $first  = AccountFakes::secretOf($this->fakes->resets[0]->resetUrl);
        $second = AccountFakes::secretOf($this->fakes->resets[1]->resetUrl);

        try {
            $this->reset($first, 'nouveau-mot');
            self::fail('A replaced link was expected to be refused.');
        } catch (InvalidAccountTokenException) {
        }

        $this->reset($second, 'nouveau-mot');
        self::assertSame('hashed:nouveau-mot', $this->users->findById($user->id())?->passwordHash());
        self::assertSame(['identity-access.user-password.changed.v1'], $this->fakes->eventNames());

        $this->expectException(InvalidAccountTokenException::class);
        $this->reset($second, 'encore-un-autre');
    }

    public function testAResetLinkIsNotAnInvitationAndNeedsAnActiveAccount(): void
    {
        $userId = $this->register('hugo@example.com')->userId;
        $this->invite($userId, self::CLINIC_A);
        $invitation = AccountFakes::secretOf($this->fakes->invitations[0]->activationUrl);

        $claire = $this->existing('claire@example.com', UserStatus::ACTIVE, UserType::CLINIC);
        $this->requestReset('claire@example.com');
        $reset = AccountFakes::secretOf($this->fakes->resets[0]->resetUrl);

        foreach ([[$invitation, 'reset'], [$reset, 'activate']] as [$secret, $use]) {
            try {
                'reset' === $use ? $this->reset($secret, 'un-mot-de-passe') : $this->activate($secret, 'un-mot-de-passe');
                self::fail('A link of another kind was expected to be refused.');
            } catch (InvalidAccountTokenException) {
            }
        }

        try {
            $this->reset($reset, 'court');
            self::fail('A short password was expected to be refused.');
        } catch (UnacceptablePasswordException) {
        }

        $this->users->save(User::reconstitute($claire->id(), 'claire@example.com', '$h', new \DateTimeImmutable(), UserStatus::DISABLED, null, UserType::CLINIC));
        $this->expectException(InvalidAccountTokenException::class);
        $this->reset($reset, 'un-mot-de-passe');
    }

    public function testAnAbsurdlyLongPasswordIsRefused(): void
    {
        $this->expectExceptionMessage('Ce mot de passe est trop long.');
        UnacceptablePasswordException::check(str_repeat('a', 4097));
    }

    private function existing(string $email, UserStatus $status, UserType $type): User
    {
        $user = User::reconstitute(UserId::fromString($this->fakes->generate()), $email, '$h', new \DateTimeImmutable(), $status, null, $type);
        $this->users->save($user);

        return $user;
    }

    private function register(string $email): \App\System\IdentityAccess\Application\Command\RegisterInvitedUser\InvitedUserRegistration
    {
        return new RegisterInvitedUserHandler($this->users, $this->fakes, $this->clock, $this->fakes->publisher())(new RegisterInvitedUser($email));
    }

    private function invite(string $userId, string $clinicId): ?string
    {
        return new SendInvitationHandler($this->users, $this->tokens, $this->fakes, $this->clock, $this->fakes, $this->fakes)(
            new SendInvitation($userId, $clinicId, 'Dr Claire Lambert', 'Clinique Montmartre', 'Paris', 'Hugo', 'Vétérinaire'),
        );
    }

    private function activate(string $secret, string $password): ActivationResult
    {
        return new ActivateInvitedUserHandler($this->users, $this->tokens, $this->fakes, $this->clock, $this->fakes->publisher())(new ActivateInvitedUser($secret, $password));
    }

    private function requestReset(string $email): void
    {
        new RequestPasswordResetHandler($this->users, $this->tokens, $this->fakes, $this->clock, $this->fakes, $this->fakes)(new RequestPasswordReset($email));
    }

    private function reset(string $secret, string $password): void
    {
        new ResetPasswordHandler($this->users, $this->tokens, $this->fakes, $this->clock, $this->fakes->publisher())(new ResetPassword($secret, $password));
    }
}
