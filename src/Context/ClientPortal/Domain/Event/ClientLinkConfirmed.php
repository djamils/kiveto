<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

/** An owner account now reaches this clinic client (first acceptance or reactivation). */
final readonly class ClientLinkConfirmed extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'client_portal';
    protected const int    VERSION         = 1;

    public function __construct(
        public string $linkId,
        public string $userId,
        public string $clinicId,
        public string $clientId,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->linkId;
    }

    public function payload(): array
    {
        return [
            'linkId'   => $this->linkId,
            'userId'   => $this->userId,
            'clinicId' => $this->clinicId,
            'clientId' => $this->clientId,
        ];
    }
}
