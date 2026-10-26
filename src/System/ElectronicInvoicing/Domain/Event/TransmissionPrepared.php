<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

/**
 * A change of an outbound transmission; the sequence is its version after
 * the change, strictly increasing.
 */
final readonly class TransmissionPrepared extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'electronic_invoicing';
    protected const int    VERSION         = 1;

    public function __construct(
        public string $transmissionId,
        public string $clinicId,
        public string $trackingId,
        public int $sequence,
        public string $occurredAt,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->transmissionId;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'transmissionId' => $this->transmissionId,
            'clinicId'       => $this->clinicId,
            'trackingId'     => $this->trackingId,
            'sequence'       => $this->sequence,
            'occurredAt'     => $this->occurredAt,
        ];
    }
}
