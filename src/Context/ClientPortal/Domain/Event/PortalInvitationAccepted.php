<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

final readonly class PortalInvitationAccepted extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'client_portal';
    protected const int    VERSION         = 1;

    public function __construct(
        public string $invitationId,
        public string $clinicId,
        public string $clientId,
        public string $userId,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->invitationId;
    }

    public function payload(): array
    {
        return [
            'invitationId' => $this->invitationId,
            'clinicId'     => $this->clinicId,
            'clientId'     => $this->clientId,
            'userId'       => $this->userId,
        ];
    }
}
