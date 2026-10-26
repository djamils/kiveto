<?php

declare(strict_types=1);

namespace App\System\Notification\Domain\Event;

use App\Shared\Domain\Event\AbstractIntegrationEvent;

/**
 * Where an outbound message now stands, published for every status it takes
 * (including the one it is created in). Consumers keep the event with the
 * highest sequence and must accept duplicates and out-of-order deliveries.
 * The labels are French and readable without colour.
 */
final readonly class OutboundMessageStatusChangedIntegrationEvent extends AbstractIntegrationEvent
{
    protected const string BOUNDED_CONTEXT = 'notification';
    protected const int    VERSION         = 1;

    public function __construct(
        public string $id,
        public ?string $clinicId,
        public ?string $clientId,
        public string $purpose,
        public string $channel,
        public string $recipientLabel,
        public string $subjectLine,
        public string $status,
        public string $statusLabel,
        public ?string $reason,
        public string $occurredAt,
        public int $sequence,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'id'             => $this->id,
            'clinicId'       => $this->clinicId,
            'clientId'       => $this->clientId,
            'purpose'        => $this->purpose,
            'channel'        => $this->channel,
            'recipientLabel' => $this->recipientLabel,
            'subjectLine'    => $this->subjectLine,
            'status'         => $this->status,
            'statusLabel'    => $this->statusLabel,
            'reason'         => $this->reason,
            'occurredAt'     => $this->occurredAt,
            'sequence'       => $this->sequence,
        ];
    }
}
