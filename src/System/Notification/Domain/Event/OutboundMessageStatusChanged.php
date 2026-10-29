<?php

declare(strict_types=1);

namespace App\System\Notification\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

/**
 * A message took a new status — including the one it is created in. The
 * sequence is the length of its status history, strictly increasing.
 */
final readonly class OutboundMessageStatusChanged extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'notification';
    protected const int    VERSION         = 1;

    public function __construct(
        public string $messageId,
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
        return $this->messageId;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'messageId'      => $this->messageId,
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
