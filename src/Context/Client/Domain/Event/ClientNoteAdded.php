<?php

declare(strict_types=1);

namespace App\Context\Client\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

readonly class ClientNoteAdded extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'client';
    protected const int    VERSION         = 1;

    public function __construct(
        private string $noteId,
        private string $clientId,
        private string $clinicId,
        private string $kind,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->noteId;
    }

    public function payload(): array
    {
        return [
            'noteId'   => $this->noteId,
            'clientId' => $this->clientId,
            'clinicId' => $this->clinicId,
            'kind'     => $this->kind,
        ];
    }
}
