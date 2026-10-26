<?php

declare(strict_types=1);

namespace App\System\Notification\Domain\Message;

use App\System\Notification\Domain\Exception\OutboundMessageNotFoundException;

interface OutboundMessageRepositoryInterface
{
    public function save(OutboundMessage $message): void;

    /**
     * @throws OutboundMessageNotFoundException when there is no such message
     */
    public function get(OutboundMessageId $id): OutboundMessage;

    public function findByIdempotencyKey(IdempotencyKey $key): ?OutboundMessage;

    public function findByExternalId(Channel $channel, string $externalMessageId): ?OutboundMessage;

    /**
     * Segments of the clinic's SMS handed to the provider (`sent_at`) in
     * [$from, $to[ — never QUEUED, BLOCKED_CAP or an SMS failed before hand-over.
     */
    public function sumBilledSegments(string $clinicId, \DateTimeImmutable $from, \DateTimeImmutable $to): int;

    /** Segments of the clinic's SMS still waiting to be handed to the provider. */
    public function sumQueuedSegments(string $clinicId): int;
}
