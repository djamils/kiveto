<?php

declare(strict_types=1);

namespace App\Context\Regulatory\Domain;

use App\Context\Regulatory\Domain\Event\MairieNotificationCancelled;
use App\Context\Regulatory\Domain\Event\MairieNotificationScheduled;
use App\Context\Regulatory\Domain\Event\MairieNotificationSent;
use App\Context\Regulatory\Domain\ValueObject\MairieNotificationId;
use App\Context\Regulatory\Domain\ValueObject\MairieNotificationStatus;
use App\Shared\Domain\Aggregate\AggregateRoot;

/**
 * Tracks the mandatory Mairie notification triggered by unidentified animal intake.
 * Deadline: admissionOpenedAt + 48 calendar hours (legal text — not working hours).
 */
final class MairieNotification extends AggregateRoot
{
    private function __construct(
        private readonly MairieNotificationId $id,
        private readonly string $admissionId,
        private readonly string $patientId,
        private readonly string $clinicId,
        private MairieNotificationStatus $status,
        private readonly \DateTimeImmutable $deadline,
        private int $version,
        private readonly \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    /**
     * Schedules a new Mairie notification.
     * Deadline is admissionOpenedAt + 48 calendar hours.
     */
    public static function schedule(
        MairieNotificationId $id,
        string $admissionId,
        string $patientId,
        string $clinicId,
        \DateTimeImmutable $admissionOpenedAt,
        \DateTimeImmutable $now,
    ): self {
        $deadline = $admissionOpenedAt->modify('+48 hours');

        $notification = new self(
            id: $id,
            admissionId: $admissionId,
            patientId: $patientId,
            clinicId: $clinicId,
            status: MairieNotificationStatus::Pending,
            deadline: $deadline,
            version: 1,
            createdAt: $now,
            updatedAt: $now,
        );

        $notification->recordDomainEvent(new MairieNotificationScheduled(
            notificationId: $id->value(),
            admissionId: $admissionId,
            clinicId: $clinicId,
            deadline: $deadline->format(\DateTimeInterface::ATOM),
        ));

        return $notification;
    }

    public function markAsSent(\DateTimeImmutable $sentAt): void
    {
        $this->status    = MairieNotificationStatus::Sent;
        $this->updatedAt = $sentAt;

        $this->recordDomainEvent(new MairieNotificationSent(
            notificationId: $this->id->value(),
            sentAt: $sentAt->format(\DateTimeInterface::ATOM),
        ));
    }

    public function cancel(string $reason, \DateTimeImmutable $now): void
    {
        $this->status    = MairieNotificationStatus::Cancelled;
        $this->updatedAt = $now;

        $this->recordDomainEvent(new MairieNotificationCancelled(
            notificationId: $this->id->value(),
            reason: $reason,
            cancelledAt: $now->format(\DateTimeInterface::ATOM),
        ));
    }

    /**
     * Reconstitutes the aggregate from persistence without firing domain events.
     */
    public static function reconstituteFromPersistence(
        MairieNotificationId $id,
        string $admissionId,
        string $patientId,
        string $clinicId,
        MairieNotificationStatus $status,
        \DateTimeImmutable $deadline,
        int $version,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            admissionId: $admissionId,
            patientId: $patientId,
            clinicId: $clinicId,
            status: $status,
            deadline: $deadline,
            version: $version,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    public function id(): MairieNotificationId
    {
        return $this->id;
    }

    public function admissionId(): string
    {
        return $this->admissionId;
    }

    public function patientId(): string
    {
        return $this->patientId;
    }

    public function clinicId(): string
    {
        return $this->clinicId;
    }

    public function status(): MairieNotificationStatus
    {
        return $this->status;
    }

    public function deadline(): \DateTimeImmutable
    {
        return $this->deadline;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
