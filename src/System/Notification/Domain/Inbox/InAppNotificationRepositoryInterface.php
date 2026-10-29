<?php

declare(strict_types=1);

namespace App\System\Notification\Domain\Inbox;

/**
 * Bell entries, always read per user and per clinic.
 */
interface InAppNotificationRepositoryInterface
{
    /**
     * @return bool false when the user already has an entry under that key (nothing written)
     */
    public function add(InAppNotification $notification): bool;

    /**
     * Marks one entry of that user read; returns whether it was unread.
     */
    public function markRead(InAppNotificationId $id, string $userId, \DateTimeImmutable $at): bool;

    /**
     * @return int how many entries of that user in that clinic were marked read
     */
    public function markAllRead(string $userId, string $clinicId, \DateTimeImmutable $at): int;

    /**
     * Deletes the unread entries posted under that key (read ones stay).
     *
     * @return int how many were deleted
     */
    public function withdrawUnread(string $idempotencyKey): int;

    /**
     * @return list<InAppNotification> the user's entries in that clinic created since $since, newest first
     */
    public function listFor(string $userId, string $clinicId, \DateTimeImmutable $since, ?int $limit): array;

    public function countUnread(string $userId, string $clinicId, \DateTimeImmutable $since): int;

    /**
     * @return int how many entries created before $before were deleted
     */
    public function purgeCreatedBefore(\DateTimeImmutable $before): int;
}
