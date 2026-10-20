<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Domain\Event;

use App\Shared\Domain\Event\AbstractIntegrationEvent;

/**
 * Where a document now stands on its way to the platform — the current view,
 * not the change that triggered it. Published on every change; consumers
 * keep the event with the highest sequence and must accept duplicates and
 * out-of-order deliveries.
 */
final readonly class InvoiceTransmissionStatusChangedIntegrationEvent extends AbstractIntegrationEvent
{
    protected const string BOUNDED_CONTEXT = 'electronic_invoicing';
    protected const int    VERSION         = 1;

    public function __construct(
        public string $clinicId,
        public string $documentId,
        public int $sequence,
        public string $state,
        public ?string $blockReason,
        public ?int $statusCode,
        public ?string $statusLabel,
        public ?string $reasonCode,
        public ?string $reasonText,
        public bool $requiresAction,
        public string $occurredAt,
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
            'clinicId'       => $this->clinicId,
            'documentId'     => $this->documentId,
            'sequence'       => $this->sequence,
            'state'          => $this->state,
            'blockReason'    => $this->blockReason,
            'statusCode'     => $this->statusCode,
            'statusLabel'    => $this->statusLabel,
            'reasonCode'     => $this->reasonCode,
            'reasonText'     => $this->reasonText,
            'requiresAction' => $this->requiresAction,
            'occurredAt'     => $this->occurredAt,
        ];
    }
}
