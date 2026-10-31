<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

/** A published document was cancelled at its source and left the Portal. */
final readonly class DocumentWithdrawn extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'client_portal';
    protected const int    VERSION         = 1;

    public function __construct(
        public string $documentId,
        public string $clinicId,
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
            'type'       => $this->type,
        ];
    }
}
