<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListPendingInvitationsForPhone;

use App\Context\ClientPortal\Application\Port\ClinicProfileInterface;
use App\Context\ClientPortal\Domain\Invitation\PortalInvitationRepositoryInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\PhoneNumber;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListPendingInvitationsForPhoneHandler
{
    public function __construct(
        private PortalInvitationRepositoryInterface $invitations,
        private ClinicProfileInterface $clinics,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return list<PendingInvitationSummary>
     */
    public function __invoke(ListPendingInvitationsForPhone $query): array
    {
        try {
            $phone = PhoneNumber::fromString($query->phone);
        } catch (\InvalidArgumentException) {
            return [];
        }

        $summaries = [];

        foreach ($this->invitations->findPendingByPhone($phone, $this->clock->now()) as $invitation) {
            $clinic      = $this->clinics->clinicOf($invitation->clinicId());
            $summaries[] = new PendingInvitationSummary($invitation->id()->toString(), $clinic->name, $clinic->city);
        }

        return $summaries;
    }
}
