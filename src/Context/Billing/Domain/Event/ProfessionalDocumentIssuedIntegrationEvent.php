<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\Event;

use App\Shared\Domain\Event\AbstractIntegrationEvent;

/**
 * A professional (B2B) invoice or credit note was issued: it must be
 * transmitted as an electronic invoice. Written in the issuing transaction
 * (outbox), so the issue never depends on the transmission.
 */
final readonly class ProfessionalDocumentIssuedIntegrationEvent extends AbstractIntegrationEvent
{
    protected const string BOUNDED_CONTEXT = 'billing';
    protected const int    VERSION         = 1;

    public function __construct(
        public string $clinicId,
        public string $documentId,
        public string $kind,
        public string $number,
        public string $issuedAt,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->documentId;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'clinicId'   => $this->clinicId,
            'documentId' => $this->documentId,
            'kind'       => $this->kind,
            'number'     => $this->number,
            'issuedAt'   => $this->issuedAt,
        ];
    }
}
