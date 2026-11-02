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

    /**
     * Messages whose content is not purged yet and that were handed over —
     * or, never handed over, created — before $before; oldest first.
     *
     * @return list<OutboundMessageId>
     */
    public function findContentToPurge(\DateTimeImmutable $before, int $limit): array;

    /**
     * Messages of the owner-account purposes (MessagePurpose::isPortalAccountMessage())
     * of that Portal account — or, older ones without account, to one of
     * these addresses — whose content is not purged yet, whatever their
     * status (QUEUED ones included: the caller cancels them or leaves them
     * to their dispatch).
     *
     * @param list<string> $addresses
     *
     * @return list<OutboundMessageId>
     */
    public function findPortalAccountMessages(string $accountId, array $addresses): array;

    /**
     * The message re-read under a row lock held until the caller's
     * transaction ends: what a concurrent dispatch committed is seen, and it
     * cannot change the row before this one is saved.
     *
     * @throws OutboundMessageNotFoundException when there is no such message
     */
    public function getForUpdate(OutboundMessageId $id): OutboundMessage;

    /**
     * In a short transaction of its own, under the row lock: claims the
     * message for its hand-over (OutboundMessage::claimForDispatch()) and
     * returns it, or null when it is no longer QUEUED (sent, failed,
     * cancelled). Once claimed, an account deletion masks it but no longer
     * cancels it.
     *
     * @throws OutboundMessageNotFoundException when there is no such message
     */
    public function claimForDispatch(OutboundMessageId $id, \DateTimeImmutable $at): ?OutboundMessage;
}
