<?php

declare(strict_types=1);

namespace App\System\Notification\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

/**
 * A message was created ready to be handed to the provider.
 */
final readonly class OutboundMessageQueued extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'notification';
    protected const int    VERSION         = 1;

    public function __construct(
        public string $messageId,
        public ?string $clinicId,
        public string $channel,
        public string $occurredAt,
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
            'messageId'  => $this->messageId,
            'clinicId'   => $this->clinicId,
            'channel'    => $this->channel,
            'occurredAt' => $this->occurredAt,
        ];
    }
}
