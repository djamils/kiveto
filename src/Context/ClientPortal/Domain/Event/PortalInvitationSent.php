<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

/** An invitation was sent, for the first time or again (`reissued`). */
final readonly class PortalInvitationSent extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'client_portal';
    protected const int    VERSION         = 1;

    public function __construct(
        public string $invitationId,
        public string $clinicId,
        public string $clientId,
        public string $channel,
        public bool $reissued,
        public string $expiresAt,
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
            'channel'      => $this->channel,
            'reissued'     => $this->reissued,
            'expiresAt'    => $this->expiresAt,
        ];
    }
}
