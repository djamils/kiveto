<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

/** A clinic document is now addressed to a client's Portal accounts. */
final readonly class DocumentPublished extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'client_portal';
    protected const int    VERSION         = 1;

    public function __construct(
        public string $documentId,
        public string $clinicId,
        public string $clientId,
        public string $type,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->documentId;
    }

    public function payload(): array
    {
        return [
            'documentId' => $this->documentId,
            'clinicId'   => $this->clinicId,
            'clientId'   => $this->clientId,
            'type'       => $this->type,
        ];
    }
}
