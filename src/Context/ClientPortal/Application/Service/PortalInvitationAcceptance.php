<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Service;

use App\Context\ClientPortal\Application\Command\AcceptPortalInvitation\AcceptOutcome;
use App\Context\ClientPortal\Application\Command\AcceptPortalInvitation\AcceptResult;
use App\Context\ClientPortal\Application\Port\ClientDirectoryInterface;
use App\Context\ClientPortal\Application\Port\IdentityClaim;
use App\Context\ClientPortal\Application\Port\IdentityClaimRequest;
use App\Context\ClientPortal\Application\Port\IdentityClaimResult;
use App\Context\ClientPortal\Application\Port\PortalIdentityInterface;
use App\Context\ClientPortal\Domain\Exception\TermsOutdatedException;
use App\Context\ClientPortal\Domain\Invitation\PortalInvitation;
use App\Context\ClientPortal\Domain\Invitation\PortalInvitationId;
use App\Context\ClientPortal\Domain\Invitation\PortalInvitationRepositoryInterface;
use App\Context\ClientPortal\Domain\Invitation\PortalInvitationStatus;
use App\Context\ClientPortal\Domain\Link\ClientLink;
use App\Context\ClientPortal\Domain\Link\ClientLinkId;
use App\Context\ClientPortal\Domain\Link\ClientLinkRepositoryInterface;
use App\Context\ClientPortal\Domain\Profile\OwnerProfile;
use App\Context\ClientPortal\Domain\Profile\OwnerProfileRepositoryInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Security\OpaqueTokenHasherInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;

/**
 * Both acceptance routes, run inside the command's transaction. The proof
 * (code or grant) is verified by IdentityAccess through the port; a bad proof
 * is an outcome, never an exception, so the attempt it cost is committed
 * (the only exception, TermsOutdatedException, is thrown before any write).
 *
 * Steps: terms check → invitation locked → retry rule on an accepted
 * invitation (same account → ACCEPTED again, nothing written) → claim of
 * the owner account → invitation accepted → link confirmed or reactivated →
 * profile created from the client file when absent, terms recorded → when
 * the phone has no pending invitation left, its onboarding grants revoked.
 */
final readonly class PortalInvitationAcceptance
{
    public function __construct(
        private PortalInvitationRepositoryInterface $invitations,
        private ClientLinkRepositoryInterface $links,
        private OwnerProfileRepositoryInterface $profiles,
        private PortalIdentityInterface $identity,
        private ClientDirectoryInterface $clients,
        private OpaqueTokenHasherInterface $hasher,
        private UuidGeneratorInterface $uuidGenerator,
        private ClockInterface $clock,
        private DomainEventPublisher $eventPublisher,
        #[Autowire('%env(PORTAL_TERMS_VERSION)%')]
        private string $currentTermsVersion,
    ) {
    }

    public function acceptWithCode(
        #[\SensitiveParameter] string $token,
        string $challengeId,
        #[\SensitiveParameter] string $code,
        string $termsVersion,
    ): AcceptOutcome {
        $this->assertCurrentTerms($termsVersion);

        $invitation = $this->invitations->findByTokenHashForUpdate($this->hasher->hash($token));

        if (null === $invitation) {
            return new AcceptOutcome(AcceptResult::INVITATION_INVALID);
        }

        $now = $this->clock->now();

        if (PortalInvitationStatus::REVOKED === $invitation->status()) {
            return new AcceptOutcome(AcceptResult::INVITATION_ALREADY_USED);
        }

        if ($invitation->isExpired($now)) {
            return new AcceptOutcome(AcceptResult::INVITATION_EXPIRED);
        }

        $claim = $this->identity->claimIdentity(new IdentityClaimRequest(
            invitationId: $invitation->id()->toString(),
            expectedPhone: $invitation->phone()->toString(),
            challengeId: $challengeId,
            code: $code,
            onboardingToken: null,
            email: $invitation->email(),
            createIfMissing: PortalInvitationStatus::PENDING === $invitation->status(),
        ));

        $result = self::resultOf($claim->result);

        if (AcceptResult::ACCEPTED !== $result) {
            return new AcceptOutcome($result);
        }

        if (PortalInvitationStatus::ACCEPTED === $invitation->status()) {
            return $this->isReplay($invitation, $claim)
                ? new AcceptOutcome(AcceptResult::ACCEPTED, $claim->userId)
                : new AcceptOutcome(AcceptResult::INVITATION_ALREADY_USED);
        }

        return $this->complete($invitation, $claim, $termsVersion, $now);
    }

    public function acceptWithGrant(string $invitationId, #[\SensitiveParameter] string $onboardingToken, string $termsVersion): AcceptOutcome
    {
        $this->assertCurrentTerms($termsVersion);

        $invitation = Uuid::isValid($invitationId)
            ? $this->invitations->findByIdForUpdate(PortalInvitationId::fromString($invitationId))
            : null;

        if (null === $invitation) {
            return new AcceptOutcome(AcceptResult::INVITATION_INVALID);
        }

        $now  = $this->clock->now();
        $open = $invitation->isOpen($now);

        $claim = $this->identity->claimIdentity(new IdentityClaimRequest(
            invitationId: $invitation->id()->toString(),
            expectedPhone: $invitation->phone()->toString(),
            challengeId: null,
            code: null,
            onboardingToken: $onboardingToken,
            email: $invitation->email(),
            createIfMissing: $open,
        ));

        $result = self::resultOf($claim->result);

        if (AcceptResult::ACCEPTED !== $result) {
            return new AcceptOutcome($result);
        }

        if ($open) {
            return $this->complete($invitation, $claim, $termsVersion, $now);
        }

        if ($this->isReplay($invitation, $claim)) {
            return new AcceptOutcome(AcceptResult::ACCEPTED, $claim->userId);
        }

        // Use-time rule: the grant (proven for this invitation's phone) is
        // worthless once that phone has nothing left to accept.
        return new AcceptOutcome([] === $this->invitations->findPendingByPhone($invitation->phone(), $now)
            ? AcceptResult::ONBOARDING_INVALID
            : AcceptResult::INVITATION_INVALID);
    }

    private function complete(PortalInvitation $invitation, IdentityClaim $claim, string $termsVersion, \DateTimeImmutable $now): AcceptOutcome
    {
        $userId = $claim->userId ?? throw new \LogicException('A proven claim names the owner account.');

        $invitation->accept($userId, $now);
        $this->invitations->save($invitation);
        $this->eventPublisher->publish($invitation);

        $this->confirmLink($userId, $invitation->clinicId(), $invitation->clientId(), $now);
        $this->recordProfile($userId, $invitation->clinicId(), $invitation->clientId(), $termsVersion, $now);

        // A plain read: locking the phone's other invitations here would
        // deadlock two acceptances of one phone. Two last acceptances racing
        // may each still see the other pending and keep the grant alive; the
        // use-time check above is the guarantee, the grant expires anyway.
        if ([] === $this->invitations->findPendingByPhone($invitation->phone(), $now)) {
            $this->identity->revokeOnboardingGrants($invitation->phone()->toString());
        }

        return new AcceptOutcome(AcceptResult::ACCEPTED, $userId);
    }

    private function confirmLink(string $userId, string $clinicId, string $clientId, \DateTimeImmutable $now): void
    {
        $link = $this->links->findForUpdate($userId, $clinicId, $clientId);

        if (null === $link) {
            $link = ClientLink::confirm(ClientLinkId::fromString($this->uuidGenerator->generate()), $userId, $clinicId, $clientId, $now);
        } else {
            $link->reactivate($now);
        }

        $this->links->save($link);
        $this->eventPublisher->publish($link);
    }

    private function recordProfile(string $userId, string $clinicId, string $clientId, string $termsVersion, \DateTimeImmutable $now): void
    {
        $profile = $this->profiles->findByUserId($userId);

        if (null === $profile) {
            $client  = $this->clients->snapshotOf($clinicId, $clientId) ?? throw new \LogicException(\sprintf('Client "%s" not found in clinic "%s".', $clientId, $clinicId));
            $profile = OwnerProfile::createFromClient($userId, $client->firstName, $client->lastName, $client->address, $termsVersion, $now);
        } else {
            $profile->acceptTerms($termsVersion, $now);
        }

        $this->profiles->save($profile);
    }

    private function assertCurrentTerms(string $termsVersion): void
    {
        if ($termsVersion !== $this->currentTermsVersion) {
            throw new TermsOutdatedException($termsVersion, $this->currentTermsVersion);
        }
    }

    /** A retry of an acceptance whose answer was lost: same invitation, same account. */
    private function isReplay(PortalInvitation $invitation, IdentityClaim $claim): bool
    {
        return PortalInvitationStatus::ACCEPTED === $invitation->status()
            && null !== $claim->userId
            && $claim->userId === $invitation->acceptedByUserId();
    }

    private static function resultOf(IdentityClaimResult $result): AcceptResult
    {
        return match ($result) {
            IdentityClaimResult::VERIFIED,
            IdentityClaimResult::ALREADY_VERIFIED      => AcceptResult::ACCEPTED,
            IdentityClaimResult::OTP_INVALID           => AcceptResult::OTP_INVALID,
            IdentityClaimResult::OTP_EXPIRED           => AcceptResult::OTP_EXPIRED,
            IdentityClaimResult::OTP_ATTEMPTS_EXCEEDED => AcceptResult::OTP_ATTEMPTS_EXCEEDED,
            IdentityClaimResult::ONBOARDING_INVALID    => AcceptResult::ONBOARDING_INVALID,
            IdentityClaimResult::PHONE_MISMATCH        => AcceptResult::INVITATION_INVALID,
            IdentityClaimResult::ACCOUNT_DISABLED      => AcceptResult::ACCOUNT_DISABLED,
        };
    }
}
