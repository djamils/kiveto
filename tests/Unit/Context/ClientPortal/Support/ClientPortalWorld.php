<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Support;

use App\Context\ClientPortal\Application\Command\SendPortalInvitation\SendPortalInvitation;
use App\Context\ClientPortal\Application\Command\SendPortalInvitation\SendPortalInvitationHandler;
use App\Context\ClientPortal\Application\Service\PortalInvitationAcceptance;
use App\Context\ClientPortal\Domain\Invitation\PortalInvitation;
use App\Context\ClientPortal\Domain\Invitation\PortalInvitationId;
use App\Shared\Application\Event\DomainEventPublisher;

/**
 * The context wired on in-memory repositories and fakes, at a settable
 * time (2026-10-29 09:00 UTC).
 */
final class ClientPortalWorld
{
    public const string CLINIC  = '01960000-0000-7000-8000-00000000c001';
    public const string CLINIC2 = '01960000-0000-7000-8000-00000000c002';
    public const string CLIENT  = '01960000-0000-7000-8000-00000000d001';
    public const string CLIENT2 = '01960000-0000-7000-8000-00000000d002';
    public const string ACTOR   = '01960000-0000-7000-8000-00000000e001';
    public const string PHONE   = '+33612345678';
    public const string TERMS   = '2026-10-29';

    public InMemoryPortalInvitations $invitations;
    public InMemoryClientLinks $links;
    public InMemoryOwnerProfiles $profiles;
    public ClientPortalFakes $fakes;
    public SettableClock $clock;

    public function __construct()
    {
        $this->invitations = new InMemoryPortalInvitations();
        $this->links       = new InMemoryClientLinks();
        $this->profiles    = new InMemoryOwnerProfiles();
        $this->fakes       = new ClientPortalFakes()->withClient(self::CLINIC, self::CLIENT);
        $this->clock       = new SettableClock(new \DateTimeImmutable('2026-10-29 09:00:00', new \DateTimeZone('UTC')));
    }

    public function publisher(): DomainEventPublisher
    {
        return new DomainEventPublisher($this->fakes);
    }

    public function sender(): SendPortalInvitationHandler
    {
        return new SendPortalInvitationHandler(
            $this->invitations,
            $this->fakes,
            $this->fakes,
            $this->fakes,
            $this->fakes,
            $this->fakes,
            $this->fakes,
            $this->fakes,
            $this->clock,
            $this->publisher(),
        );
    }

    public function acceptance(): PortalInvitationAcceptance
    {
        return new PortalInvitationAcceptance(
            $this->invitations,
            $this->links,
            $this->profiles,
            $this->fakes,
            $this->fakes,
            $this->fakes,
            $this->fakes,
            $this->clock,
            $this->publisher(),
            self::TERMS,
        );
    }

    /**
     * Sends an invitation by SMS, then clears the recorded events and messages.
     *
     * @return array{0: string, 1: string} [token, invitation id]
     */
    public function invite(string $clinicId = self::CLINIC, string $clientId = self::CLIENT): array
    {
        $id = $this->sender()(new SendPortalInvitation($clinicId, $clientId, 'sms', self::ACTOR));

        $this->fakes->events   = [];
        $message               = array_pop($this->fakes->messages);
        $this->fakes->messages = [];
        \assert(null !== $message);

        return [substr($message->link, (int) strpos($message->link, '#t=') + 3), $id];
    }

    public function invitation(string $id): PortalInvitation
    {
        return $this->invitations->invitations[PortalInvitationId::fromString($id)->toString()] ?? throw new \LogicException('No invitation ' . $id);
    }
}
