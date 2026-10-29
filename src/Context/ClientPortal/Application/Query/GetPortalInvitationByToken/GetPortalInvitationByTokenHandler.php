<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetPortalInvitationByToken;

use App\Context\ClientPortal\Application\Port\ClinicNamesInterface;
use App\Context\ClientPortal\Application\Port\PetNamesInterface;
use App\Context\ClientPortal\Domain\Exception\InvitationAlreadyUsedException;
use App\Context\ClientPortal\Domain\Exception\InvitationExpiredException;
use App\Context\ClientPortal\Domain\Exception\InvitationInvalidException;
use App\Context\ClientPortal\Domain\Invitation\PortalInvitationRepositoryInterface;
use App\Context\ClientPortal\Domain\Invitation\PortalInvitationStatus;
use App\Shared\Domain\Security\OpaqueTokenHasherInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetPortalInvitationByTokenHandler
{
    public function __construct(
        private PortalInvitationRepositoryInterface $invitations,
        private ClinicNamesInterface $clinics,
        private PetNamesInterface $pets,
        private OpaqueTokenHasherInterface $hasher,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(GetPortalInvitationByToken $query): PortalInvitationDetails
    {
        $invitation = $this->invitations->findByTokenHash($this->hasher->hash($query->token))
            ?? throw new InvitationInvalidException();

        if (PortalInvitationStatus::PENDING !== $invitation->status()) {
            throw new InvitationAlreadyUsedException($invitation->id()->toString());
        }

        if ($invitation->isExpired($this->clock->now())) {
            throw new InvitationExpiredException($invitation->id()->toString());
        }

        $clinic = $this->clinics->clinicOf($invitation->clinicId());

        return new PortalInvitationDetails(
            invitationId: $invitation->id()->toString(),
            clinicId: $invitation->clinicId(),
            clinicName: $clinic->name,
            city: $clinic->city,
            email: $invitation->email(),
            phone: $invitation->phone()->toString(),
            petNames: $this->pets->petNamesOf($invitation->clinicId(), $invitation->clientId()),
            expiresAt: $invitation->expiresAt(),
        );
    }
}
