<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\EventSubscriber;

use App\Context\Clinic\Application\Port\InvitationRevokerInterface;
use App\Context\Clinic\Domain\Staff\Event\ClinicMembershipDisabled;
use App\Context\Clinic\Domain\Staff\Event\ClinicMembershipValidityChanged;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * A disabled membership, or one whose end date is now past, revokes the
 * invitation links the clinic sent to that member — in the transaction of
 * the change. A window that only starts later keeps them: the member may
 * activate their account before their first day.
 */
final readonly class RevokeInvitationOnMembershipEnd
{
    public function __construct(
        private InvitationRevokerInterface $invitations,
        private ClockInterface $clock,
    ) {
    }

    #[AsMessageHandler(bus: 'messenger.bus.event')]
    public function onDisabled(ClinicMembershipDisabled $event): void
    {
        $payload = $event->payload();

        $this->invitations->revoke((string) $payload['userId'], (string) $payload['clinicId']);
    }

    #[AsMessageHandler(bus: 'messenger.bus.event')]
    public function onValidityChanged(ClinicMembershipValidityChanged $event): void
    {
        $payload = $event->payload();

        if (null === $payload['validUntil'] || new \DateTimeImmutable($payload['validUntil']) > $this->clock->now()) {
            return;
        }

        $this->invitations->revoke((string) $payload['userId'], (string) $payload['clinicId']);
    }
}
