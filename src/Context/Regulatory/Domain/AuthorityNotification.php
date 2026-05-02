<?php

declare(strict_types=1);

namespace App\Context\Regulatory\Domain;

use App\Context\Regulatory\Domain\Event\AuthorityNotificationCancelled;
use App\Context\Regulatory\Domain\Event\AuthorityNotificationScheduled;
use App\Context\Regulatory\Domain\Event\AuthorityNotificationSent;
use App\Context\Regulatory\Domain\Policy\RegulatoryPolicyInterface;
use App\Context\Regulatory\Domain\ValueObject\AuthorityNotificationId;
use App\Context\Regulatory\Domain\ValueObject\AuthorityNotificationStatus;
use App\Shared\Domain\Aggregate\AggregateRoot;

/**
 * Tracks the mandatory authority notification triggered by unidentified animal intake.
 * Deadline: admissionOpenedAt + 48 calendar hours (legal text — not working hours).
 */
final class AuthorityNotification extends AggregateRoot
{
    private function __construct(
        private readonly AuthorityNotificationId $id,
        private readonly string $admissionId,
        private readonly string $patientId,
        private readonly string $clinicId,
        private AuthorityNotificationStatus $status,
        private readonly \DateTimeImmutable $deadline,
        private int $version,
        private readonly \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    /**
     * Schedules a new authority notification.
     * Deadline is determined by the jurisdiction policy.
     */
    public static function schedule(
        AuthorityNotificationId $id,
        string $admissionId,
        string $patientId,
        string $clinicId,
        \DateTimeImmutable $admissionOpenedAt,
        RegulatoryPolicyInterface $policy,
        \DateTimeImmutable $now,
    ): self {
        $deadline = $policy->getAuthorityNotificationDeadline($admissionOpenedAt);

        $notification = new self(
            id: $id,
            admissionId: $admissionId,
            patientId: $patientId,
            clinicId: $clinicId,
            status: AuthorityNotificationStatus::Pending,
            deadline: $deadline,
            version: 1,
            createdAt: $now,
            updatedAt: $now,
        );

        $notification->recordDomainEvent(new AuthorityNotificationScheduled(
            notificationId: $id->value(),
            admissionId: $admissionId,
            clinicId: $clinicId,
            deadline: $deadline->format(\DateTimeInterface::ATOM),
        ));

        return $notification;
    }

    public function markAsSent(\DateTimeImmutable $sentAt): void
    {
        $this->status    = AuthorityNotificationStatus::Sent;
        $this->updatedAt = $sentAt;

        $this->recordDomainEvent(new AuthorityNotificationSent(
            notificationId: $this->id->value(),
            sentAt: $sentAt->format(\DateTimeInterface::ATOM),
        ));
    }

    public function cancel(string $reason, \DateTimeImmutable $now): void
    {
        $this->status    = AuthorityNotificationStatus::Cancelled;
        $this->updatedAt = $now;

        $this->recordDomainEvent(new AuthorityNotificationCancelled(
            notificationId: $this->id->value(),
            reason: $reason,
            cancelledAt: $now->format(\DateTimeInterface::ATOM),
        ));
    }

    /**
     * Reconstitutes the aggregate from persistence without firing domain events.
     */
    public static function reconstituteFromPersistence(
        AuthorityNotificationId $id,
        string $admissionId,
        string $patientId,
        string $clinicId,
        AuthorityNotificationStatus $status,
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

    public function id(): AuthorityNotificationId
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

    public function status(): AuthorityNotificationStatus
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
