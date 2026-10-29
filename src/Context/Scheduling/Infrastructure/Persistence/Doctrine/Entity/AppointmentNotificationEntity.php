<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Rows are read and written in SQL by
 * DbalAppointmentNotificationStateRepository (upsert, row lock).
 */
#[ORM\Entity]
#[ORM\Table(name: 'scheduling__appointment_notifications')]
#[ORM\Index(name: 'idx_sched_notif_clinic', columns: ['clinic_id'])]
#[ORM\Index(name: 'idx_sched_notif_reminder_due', columns: ['reminder_due_at_utc'])]
class AppointmentNotificationEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'appointment_id', type: UuidType::NAME)]
        private Uuid $appointmentId,
        #[ORM\Column(name: 'clinic_id', type: UuidType::NAME)]
        private Uuid $clinicId,
        #[ORM\Column(name: 'confirmed_at', type: 'datetime_immutable', nullable: true)]
        private ?\DateTimeImmutable $confirmedAt = null,
        #[ORM\Column(name: 'cancellation_notified_at', type: 'datetime_immutable', nullable: true)]
        private ?\DateTimeImmutable $cancellationNotifiedAt = null,
        #[ORM\Column(type: 'boolean', options: ['default' => false])]
        private bool $silenced = false,
        #[ORM\Column(name: 'notified_starts_at_utc', type: 'datetime_immutable', nullable: true)]
        private ?\DateTimeImmutable $notifiedStartsAtUtc = null,
        #[ORM\Column(name: 'notified_duration', type: 'integer', nullable: true)]
        private ?int $notifiedDuration = null,
        #[ORM\Column(name: 'notified_practitioner_user_id', type: UuidType::NAME, nullable: true)]
        private ?Uuid $notifiedPractitionerUserId = null,
        #[ORM\Column(name: 'notification_counter', type: 'integer', options: ['default' => 0])]
        private int $notificationCounter = 0,
        #[ORM\Column(name: 'reminder_due_at_utc', type: 'datetime_immutable', nullable: true)]
        private ?\DateTimeImmutable $reminderDueAtUtc = null,
        #[ORM\Column(name: 'reminder_requested_at', type: 'datetime_immutable', nullable: true)]
        private ?\DateTimeImmutable $reminderRequestedAt = null,
        #[ORM\Column(name: 'notified_owner_id', type: UuidType::NAME, nullable: true)]
        private ?Uuid $notifiedOwnerId = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function columns(): array
    {
        return [
            'appointment_id'                => $this->appointmentId,
            'clinic_id'                     => $this->clinicId,
            'confirmed_at'                  => $this->confirmedAt,
            'cancellation_notified_at'      => $this->cancellationNotifiedAt,
            'silenced'                      => $this->silenced,
            'notified_starts_at_utc'        => $this->notifiedStartsAtUtc,
            'notified_duration'             => $this->notifiedDuration,
            'notified_practitioner_user_id' => $this->notifiedPractitionerUserId,
            'notification_counter'          => $this->notificationCounter,
            'reminder_due_at_utc'           => $this->reminderDueAtUtc,
            'reminder_requested_at'         => $this->reminderRequestedAt,
            'notified_owner_id'             => $this->notifiedOwnerId,
        ];
    }
}
