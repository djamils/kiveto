<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Service;

use App\Context\ClientPortal\Application\Command\AcceptPortalInvitation\AcceptOutcome;
use App\Context\ClientPortal\Application\Command\AcceptPortalInvitation\AcceptResult;
use App\Context\ClientPortal\Application\Command\AcceptPortalInvitationWithCode\AcceptPortalInvitationWithCode;
use App\Context\ClientPortal\Application\Command\AcceptPortalInvitationWithCode\AcceptPortalInvitationWithCodeHandler;
use App\Context\ClientPortal\Application\Command\AcceptPortalInvitationWithGrant\AcceptPortalInvitationWithGrant;
use App\Context\ClientPortal\Application\Command\AcceptPortalInvitationWithGrant\AcceptPortalInvitationWithGrantHandler;
use App\Context\ClientPortal\Application\Port\IdentityClaimResult;
use App\Context\ClientPortal\Domain\Exception\TermsOutdatedException;
use App\Context\ClientPortal\Domain\Invitation\PortalInvitationStatus;
use App\Context\ClientPortal\Domain\Link\ClientLink;
use App\Context\ClientPortal\Domain\Link\ClientLinkId;
use App\Context\ClientPortal\Domain\Link\ClientLinkStatus;
use App\Context\ClientPortal\Domain\Profile\OwnerProfile;
use App\Tests\Unit\Context\ClientPortal\Support\ClientPortalFakes;
use App\Tests\Unit\Context\ClientPortal\Support\ClientPortalWorld as W;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PortalInvitationAcceptanceTest extends TestCase
{
    private const string USER      = ClientPortalFakes::USER;
    private const string CHALLENGE = '01960000-0000-7000-8000-0000000000c1';

    // ---- Link flow (code) ----------------------------------------------------

    public function testTheCodeAcceptsThePendingInvitation(): void
    {
        $world        = new W();
        [$token, $id] = $world->invite();
        $world->clock->advance('+1 hour');

        $outcome = new AcceptPortalInvitationWithCodeHandler($world->acceptance())(new AcceptPortalInvitationWithCode($token, self::CHALLENGE, '123456', W::TERMS));

        self::assertSame([AcceptResult::ACCEPTED, self::USER], [$outcome->result, $outcome->userId]);

        [$claim] = $world->fakes->claims;
        self::assertSame([$id, W::PHONE, self::CHALLENGE, '123456', null, 'sophie@example.com', true], [
            $claim->invitationId, $claim->expectedPhone, $claim->challengeId, $claim->code, $claim->onboardingToken, $claim->email, $claim->createIfMissing,
        ]);

        $invitation = $world->invitation($id);
        self::assertSame([PortalInvitationStatus::ACCEPTED, self::USER, '10:00'], [$invitation->status(), $invitation->acceptedByUserId(), $invitation->acceptedAt()?->format('H:i')]);

        [$link] = array_values($world->links->links);
        self::assertSame([self::USER, W::CLINIC, W::CLIENT, ClientLinkStatus::CONFIRMED, '10:00'], [$link->userId(), $link->clinicId(), $link->clientId(), $link->status(), $link->linkedAt()?->format('H:i')]);

        $profile = $world->profiles->profiles[self::USER];
        self::assertSame(['Sophie', 'Martin', '12 rue des Lilas', 'Bât. B', '69003', 'Lyon', 'FR', W::TERMS, '10:00'], [
            $profile->firstName(), $profile->lastName(), $profile->address()?->streetLine1, $profile->address()?->streetLine2, $profile->address()?->postalCode, $profile->address()?->city, $profile->address()?->countryCode, $profile->termsVersion(), $profile->termsAcceptedAt()->format('H:i'),
        ]);

        self::assertSame(['client_portal.portal-invitation.accepted.v1', 'client_portal.client-link.confirmed.v1'], $world->fakes->eventNames());
        // Locks: the invitation first, the phone's remaining invitations last.
        self::assertSame(['client', 'token'], $world->invitations->locks, 'the other invitations of the phone are read without lock');
        self::assertSame([W::PHONE], $world->fakes->revokedGrants, 'no pending invitation left: the grants go');
    }

    public function testGrantsStayWhileTheSamePhoneHasAnotherPendingInvitation(): void
    {
        $world = new W();
        $world->fakes->withClient(W::CLINIC2, W::CLIENT2);
        [$token] = $world->invite();
        $world->invite(W::CLINIC2, W::CLIENT2);

        $outcome = $world->acceptance()->acceptWithCode($token, self::CHALLENGE, '123456', W::TERMS);

        self::assertSame(AcceptResult::ACCEPTED, $outcome->result);
        self::assertSame([], $world->fakes->revokedGrants);
    }

    public function testAnExistingLinkIsReactivatedAndTheProfileIsNotOverwritten(): void
    {
        $world   = new W();
        [$token] = $world->invite();
        $revoked = ClientLink::confirm(ClientLinkId::fromString('01960000-0000-7000-8000-0000000000a9'), self::USER, W::CLINIC, W::CLIENT, new \DateTimeImmutable('2026-01-01'));
        $revoked->revoke(new \DateTimeImmutable('2026-02-01'));
        $world->links->save($revoked);
        $world->profiles->save(OwnerProfile::createFromClient(self::USER, 'Paul', 'Durand', null, 'old', new \DateTimeImmutable('2026-01-01')));

        $world->acceptance()->acceptWithCode($token, self::CHALLENGE, '123456', W::TERMS);

        self::assertCount(1, $world->links->links);
        self::assertSame([ClientLinkStatus::CONFIRMED, '2026-10-29'], [$revoked->status(), $revoked->linkedAt()?->format('Y-m-d')]);
        $profile = $world->profiles->profiles[self::USER];
        self::assertSame(['Paul', 'Durand', null, W::TERMS, '2026-10-29', '2026-01-01'], [
            $profile->firstName(), $profile->lastName(), $profile->address(), $profile->termsVersion(), $profile->termsAcceptedAt()->format('Y-m-d'), $profile->createdAt()->format('Y-m-d'),
        ]);
    }

    public function testOutdatedTermsAreRefusedBeforeAnything(): void
    {
        $world                     = new W();
        [$token]                   = $world->invite();
        $world->invitations->locks = [];

        try {
            $world->acceptance()->acceptWithCode($token, self::CHALLENGE, '123456', 'old');
            self::fail('Outdated terms');
        } catch (TermsOutdatedException $e) {
            self::assertSame(['old', W::TERMS, 'Terms version "old" is not the current one ("' . W::TERMS . '").'], [$e->acceptedVersion, $e->currentVersion, $e->getMessage()]);
        }

        self::assertSame([[], []], [$world->invitations->locks, $world->fakes->claims]);
    }

    public function testAnUnknownOrReplacedTokenIsInvalid(): void
    {
        $world   = new W();
        [$token] = $world->invite();
        $world->invite(); // resend: the first token is dead

        self::assertSame(AcceptResult::INVITATION_INVALID, $world->acceptance()->acceptWithCode($token, self::CHALLENGE, '123456', W::TERMS)->result);
        self::assertSame([], $world->fakes->claims);
    }

    public function testARevokedInvitationIsAlreadyUsedAndAnExpiredOneExpiredWithoutAnyClaim(): void
    {
        $world        = new W();
        [$token, $id] = $world->invite();

        $world->clock->advance('+7 days');
        self::assertSame(AcceptResult::INVITATION_EXPIRED, $world->acceptance()->acceptWithCode($token, self::CHALLENGE, '123456', W::TERMS)->result);

        $world->invitation($id)->revoke($world->clock->now());
        self::assertSame(AcceptResult::INVITATION_ALREADY_USED, $world->acceptance()->acceptWithCode($token, self::CHALLENGE, '123456', W::TERMS)->result);

        self::assertSame([], $world->fakes->claims);
    }

    /**
     * @return iterable<string, array{0: IdentityClaimResult, 1: AcceptResult}>
     */
    public static function provideAFailedProofIsAnOutcomeAndNothingIsWrittenCases(): iterable
    {
        yield 'wrong code' => [IdentityClaimResult::OTP_INVALID, AcceptResult::OTP_INVALID];
        yield 'code expired' => [IdentityClaimResult::OTP_EXPIRED, AcceptResult::OTP_EXPIRED];
        yield 'attempts' => [IdentityClaimResult::OTP_ATTEMPTS_EXCEEDED, AcceptResult::OTP_ATTEMPTS_EXCEEDED];
        yield 'grant' => [IdentityClaimResult::ONBOARDING_INVALID, AcceptResult::ONBOARDING_INVALID];
        yield 'other phone' => [IdentityClaimResult::PHONE_MISMATCH, AcceptResult::INVITATION_INVALID];
        yield 'disabled account' => [IdentityClaimResult::ACCOUNT_DISABLED, AcceptResult::ACCOUNT_DISABLED];
    }

    #[DataProvider('provideAFailedProofIsAnOutcomeAndNothingIsWrittenCases')]
    public function testAFailedProofIsAnOutcomeAndNothingIsWritten(IdentityClaimResult $claim, AcceptResult $expected): void
    {
        foreach (['code', 'grant'] as $route) {
            $world        = new W();
            [$token, $id] = $world->invite();
            $world->fakes->answerClaims($claim);
            $saves = $world->invitations->saves;

            $outcome = 'code' === $route
                ? $world->acceptance()->acceptWithCode($token, self::CHALLENGE, '000000', W::TERMS)
                : $world->acceptance()->acceptWithGrant($id, 'grant', W::TERMS);

            self::assertSame([$expected, null], [$outcome->result, $outcome->userId], $route);
            self::assertSame(PortalInvitationStatus::PENDING, $world->invitation($id)->status());
            self::assertSame([$saves, [], [], [], []], [$world->invitations->saves, $world->links->links, $world->profiles->profiles, $world->fakes->events, $world->fakes->revokedGrants]);
        }
    }

    public function testARetryOfAnAcceptedInvitationAnswersAcceptedWithoutWriting(): void
    {
        $world   = new W();
        [$token] = $world->invite();
        $world->acceptance()->acceptWithCode($token, self::CHALLENGE, '123456', W::TERMS);
        $saves                = [$world->invitations->saves, $world->links->saves, $world->profiles->saves];
        $world->fakes->events = [];
        $world->fakes->answerClaims(IdentityClaimResult::ALREADY_VERIFIED, self::USER);

        $outcome = $world->acceptance()->acceptWithCode($token, self::CHALLENGE, '123456', W::TERMS);

        self::assertSame([AcceptResult::ACCEPTED, self::USER], [$outcome->result, $outcome->userId]);
        self::assertFalse($world->fakes->claims[1]->createIfMissing, 'retry check: never creates an account');
        self::assertSame($saves, [$world->invitations->saves, $world->links->saves, $world->profiles->saves]);
        self::assertSame([], $world->fakes->events);
    }

    public function testAnInvitationAcceptedByAnotherAccountIsAlreadyUsed(): void
    {
        $world   = new W();
        [$token] = $world->invite();
        $world->acceptance()->acceptWithCode($token, self::CHALLENGE, '123456', W::TERMS);

        $world->fakes->answerClaims(IdentityClaimResult::ALREADY_VERIFIED, 'another-user');
        self::assertSame(AcceptResult::INVITATION_ALREADY_USED, $world->acceptance()->acceptWithCode($token, self::CHALLENGE, '123456', W::TERMS)->result);

        $world->fakes->answerClaims(IdentityClaimResult::VERIFIED);
        self::assertSame(AcceptResult::INVITATION_ALREADY_USED, $world->acceptance()->acceptWithCode($token, self::CHALLENGE, '123456', W::TERMS)->result, 'no account for the phone');

        // A consumed code presented after its expiry is just a wrong code.
        $world->fakes->answerClaims(IdentityClaimResult::OTP_INVALID);
        self::assertSame(AcceptResult::OTP_INVALID, $world->acceptance()->acceptWithCode($token, self::CHALLENGE, '123456', W::TERMS)->result);
    }

    public function testAProvenClaimWithoutAccountIsALogicError(): void
    {
        $world   = new W();
        [$token] = $world->invite();
        $world->fakes->answerClaims(IdentityClaimResult::VERIFIED);

        $this->expectException(\LogicException::class);
        $world->acceptance()->acceptWithCode($token, self::CHALLENGE, '123456', W::TERMS);
    }

    public function testAProfileNeedsTheClientFile(): void
    {
        $world                   = new W();
        [$token]                 = $world->invite();
        $world->fakes->snapshots = [];

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Client "' . W::CLIENT . '" not found in clinic "' . W::CLINIC . '".');
        $world->acceptance()->acceptWithCode($token, self::CHALLENGE, '123456', W::TERMS);
    }

    // ---- Phone flow (grant) --------------------------------------------------

    public function testTheGrantAcceptsThePendingInvitation(): void
    {
        $world  = new W();
        [, $id] = $world->invite();

        $outcome = new AcceptPortalInvitationWithGrantHandler($world->acceptance())(new AcceptPortalInvitationWithGrant($id, 'grant-token', W::TERMS));

        self::assertSame([AcceptResult::ACCEPTED, self::USER], [$outcome->result, $outcome->userId]);
        [$claim] = $world->fakes->claims;
        self::assertSame([$id, W::PHONE, null, null, 'grant-token', true], [$claim->invitationId, $claim->expectedPhone, $claim->challengeId, $claim->code, $claim->onboardingToken, $claim->createIfMissing]);
        self::assertSame(['client', 'id:' . $id], $world->invitations->locks);
        self::assertCount(1, $world->links->links);
        self::assertSame([W::PHONE], $world->fakes->revokedGrants);
    }

    public function testAnUnknownOrMalformedInvitationIdIsInvalid(): void
    {
        $world = new W();
        $world->invite();
        $world->invitations->locks = [];

        self::assertSame(AcceptResult::INVITATION_INVALID, $world->acceptance()->acceptWithGrant('not-a-uuid', 'grant', W::TERMS)->result);
        self::assertSame([], $world->invitations->locks);
        self::assertSame(AcceptResult::INVITATION_INVALID, $world->acceptance()->acceptWithGrant('01960000-0000-7000-8000-0000000000ff', 'grant', W::TERMS)->result);
        self::assertSame([], $world->fakes->claims);
    }

    public function testTheGrantRouteChecksTheTermsFirst(): void
    {
        $world  = new W();
        [, $id] = $world->invite();

        $this->expectException(TermsOutdatedException::class);
        $world->acceptance()->acceptWithGrant($id, 'grant', '1999-01-01');
    }

    public function testAGrantRetryOfAnAcceptedInvitationAnswersAccepted(): void
    {
        $world  = new W();
        [, $id] = $world->invite();
        $world->acceptance()->acceptWithGrant($id, 'grant', W::TERMS);
        $saves = $world->invitations->saves;

        $outcome = $world->acceptance()->acceptWithGrant($id, 'grant', W::TERMS);

        self::assertSame([AcceptResult::ACCEPTED, self::USER], [$outcome->result, $outcome->userId]);
        self::assertFalse($world->fakes->claims[1]->createIfMissing);
        self::assertSame($saves, $world->invitations->saves);
    }

    public function testAGrantOnAnInvitationNoLongerPendingFollowsTheUseTimeRule(): void
    {
        $world = new W();
        $world->fakes->withClient(W::CLINIC2, W::CLIENT2);
        [, $id]    = $world->invite();
        [, $other] = $world->invite(W::CLINIC2, W::CLIENT2);

        // Accepted by another account, while the phone still has an invitation pending.
        $world->invitation($id)->accept('another-user', $world->clock->now());
        self::assertSame(AcceptResult::INVITATION_INVALID, $world->acceptance()->acceptWithGrant($id, 'grant', W::TERMS)->result);
        self::assertFalse($world->fakes->claims[0]->createIfMissing);

        // The clinic revoked the last pending one meanwhile: the grant is worthless.
        $world->invitation($other)->revoke($world->clock->now());
        self::assertSame(AcceptResult::ONBOARDING_INVALID, $world->acceptance()->acceptWithGrant($other, 'grant', W::TERMS)->result);
        self::assertSame(AcceptResult::ONBOARDING_INVALID, $world->acceptance()->acceptWithGrant($id, 'grant', W::TERMS)->result);
        self::assertSame([[], []], [$world->links->links, $world->fakes->events]);
    }

    public function testAGrantOnAnExpiredInvitationIsWorthlessOnceNothingIsLeft(): void
    {
        $world  = new W();
        [, $id] = $world->invite();
        $world->clock->advance('+7 days');

        self::assertSame(AcceptResult::ONBOARDING_INVALID, $world->acceptance()->acceptWithGrant($id, 'grant', W::TERMS)->result);
        self::assertSame(PortalInvitationStatus::PENDING, $world->invitation($id)->status());
    }

    public function testTheOutcomeDefaultsToNoAccount(): void
    {
        self::assertNull(new AcceptOutcome(AcceptResult::OTP_INVALID)->userId);
    }
}
