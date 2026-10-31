<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\SendPortalInvitation;

use App\Context\ClientPortal\Application\Port\ClientDirectoryInterface;
use App\Context\ClientPortal\Application\Port\ClinicProfileInterface;
use App\Context\ClientPortal\Application\Port\InvitationLinkBuilderInterface;
use App\Context\ClientPortal\Application\Port\MobilePhonePolicyInterface;
use App\Context\ClientPortal\Application\Port\NotificationRequesterInterface;
use App\Context\ClientPortal\Application\Port\PortalClientContact;
use App\Context\ClientPortal\Application\Port\PortalInvitationMessage;
use App\Context\ClientPortal\Domain\Exception\ClientNotInvitableException;
use App\Context\ClientPortal\Domain\Invitation\InvitationChannel;
use App\Context\ClientPortal\Domain\Invitation\PortalInvitation;
use App\Context\ClientPortal\Domain\Invitation\PortalInvitationId;
use App\Context\ClientPortal\Domain\Invitation\PortalInvitationRepositoryInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Security\OpaqueTokenHasherInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\PhoneNumber;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * A pending invitation of the client (expired or not) is reissued — same
 * id, new token, new expiry; otherwise a new one is created. The message is
 * requested last (Notification's SMS cap lock).
 */
#[AsMessageHandler]
final readonly class SendPortalInvitationHandler
{
    private const string DISPLAY_TIME_ZONE = 'Europe/Paris';

    public function __construct(
        private PortalInvitationRepositoryInterface $invitations,
        private ClientDirectoryInterface $clients,
        private MobilePhonePolicyInterface $mobilePolicy,
        private ClinicProfileInterface $clinics,
        private InvitationLinkBuilderInterface $links,
        private NotificationRequesterInterface $notifications,
        private OpaqueTokenHasherInterface $hasher,
        private UuidGeneratorInterface $uuidGenerator,
        private ClockInterface $clock,
        private DomainEventPublisher $eventPublisher,
    ) {
    }

    public function __invoke(SendPortalInvitation $command): string
    {
        $channel = InvitationChannel::tryFrom($command->channel)
            ?? throw new \InvalidArgumentException(\sprintf('Unknown invitation channel "%s".', $command->channel));

        $contact = $this->invitableContact($command->clinicId, $command->clientId, $channel);
        \assert(null !== $contact->mobile);
        $phone = PhoneNumber::fromString($contact->mobile);

        $now       = $this->clock->now();
        $token     = $this->hasher->newSecret();
        $tokenHash = $this->hasher->hash($token);

        $invitation = $this->invitations->findPendingForClientForUpdate($command->clinicId, $command->clientId)[0] ?? null;

        if (null !== $invitation) {
            $invitation->reissue($tokenHash, $channel, $phone, $contact->email, $now);
        } else {
            $invitation = PortalInvitation::send(
                id: PortalInvitationId::fromString($this->uuidGenerator->generate()),
                clinicId: $command->clinicId,
                clientId: $command->clientId,
                phone: $phone,
                email: $contact->email,
                channel: $channel,
                tokenHash: $tokenHash,
                now: $now,
            );
        }

        $this->invitations->save($invitation);
        $this->eventPublisher->publish($invitation);

        $this->notifications->requestPortalInvitation(new PortalInvitationMessage(
            invitationId: $invitation->id()->toString(),
            clinicId: $command->clinicId,
            clientId: $command->clientId,
            channel: $channel->value,
            recipientAddress: InvitationChannel::EMAIL === $channel ? (string) $contact->email : $phone->toString(),
            recipientName: $contact->displayName,
            clinicName: $this->clinics->clinicOf($command->clinicId)->name,
            ownerFirstName: $contact->firstName,
            link: $this->links->invitationLink($token),
            expiresOn: $invitation->expiresAt()->setTimezone(new \DateTimeZone(self::DISPLAY_TIME_ZONE))->format('d/m/Y'),
            sentAt: $invitation->sentAt(),
        ));

        return $invitation->id()->toString();
    }

    private function invitableContact(string $clinicId, string $clientId, InvitationChannel $channel): PortalClientContact
    {
        $contact = $this->clients->contactOf($clinicId, $clientId);

        $reason = match (true) {
            null === $contact                                                 => ClientNotInvitableException::UNKNOWN_CLIENT,
            $contact->archived                                                => ClientNotInvitableException::ARCHIVED,
            null === $contact->mobile                                         => ClientNotInvitableException::NO_MOBILE,
            !$this->mobilePolicy->isPortalMobile($contact->mobile)            => ClientNotInvitableException::MOBILE_NOT_SUPPORTED,
            InvitationChannel::EMAIL === $channel && null === $contact->email => ClientNotInvitableException::NO_EMAIL,
            default                                                           => null,
        };

        if (null !== $reason) {
            throw new ClientNotInvitableException($reason);
        }

        \assert(null !== $contact);

        return $contact;
    }
}
