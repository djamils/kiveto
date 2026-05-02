<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Regulatory;

use App\Context\Regulatory\Domain\AuthorityNotification;
use App\Context\Regulatory\Domain\Event\AuthorityNotificationCancelled;
use App\Context\Regulatory\Domain\Event\AuthorityNotificationScheduled;
use App\Context\Regulatory\Domain\Event\AuthorityNotificationSent;
use App\Context\Regulatory\Domain\Policy\RegulatoryPolicyInterface;
use App\Context\Regulatory\Domain\ValueObject\AuthorityNotificationId;
use App\Context\Regulatory\Domain\ValueObject\AuthorityNotificationStatus;
use PHPUnit\Framework\TestCase;

final class AuthorityNotificationTest extends TestCase
{
    private const string NOTIFICATION_ID = '11111111-1111-1111-1111-111111111111';
    private const string ADMISSION_ID    = '22222222-2222-2222-2222-222222222222';
    private const string PATIENT_ID      = '33333333-3333-3333-3333-333333333333';
    private const string CLINIC_ID       = '44444444-4444-4444-4444-444444444444';

    private RegulatoryPolicyInterface $policy;

    protected function setUp(): void
    {
        $this->policy = $this->createStub(RegulatoryPolicyInterface::class);
        $this->policy->method('getAuthorityNotificationDeadline')
            ->willReturnCallback(static fn (\DateTimeImmutable $date): \DateTimeImmutable => $date->modify('+48 hours'))
        ;
        $this->policy->method('getStrayCustodyDeadline')
            ->willReturnCallback(static fn (\DateTimeImmutable $date): \DateTimeImmutable => $date->modify('+8 days'))
        ;
    }

    public function testScheduleSetsPendingStatus(): void
    {
        $now      = $this->makeNow();
        $openedAt = $this->makeOpenedAt();

        $notification = AuthorityNotification::schedule(
            id: AuthorityNotificationId::fromString(self::NOTIFICATION_ID),
            admissionId: self::ADMISSION_ID,
            patientId: self::PATIENT_ID,
            clinicId: self::CLINIC_ID,
            admissionOpenedAt: $openedAt,
            policy: $this->policy,
            now: $now,
        );

        self::assertSame(AuthorityNotificationStatus::Pending, $notification->status());
        self::assertSame('2026-04-03T08:00:00+00:00', $notification->deadline()->format(\DateTimeInterface::ATOM));
        self::assertSame(self::ADMISSION_ID, $notification->admissionId());
        self::assertSame(self::PATIENT_ID, $notification->patientId());
        self::assertSame(self::CLINIC_ID, $notification->clinicId());
        self::assertSame(1, $notification->version());

        $events = $notification->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(AuthorityNotificationScheduled::class, $events[0]);
    }

    public function testScheduleEmitsAuthorityNotificationScheduledEvent(): void
    {
        $now          = $this->makeNow();
        $openedAt     = $this->makeOpenedAt();
        $notification = AuthorityNotification::schedule(
            id: AuthorityNotificationId::fromString(self::NOTIFICATION_ID),
            admissionId: self::ADMISSION_ID,
            patientId: self::PATIENT_ID,
            clinicId: self::CLINIC_ID,
            admissionOpenedAt: $openedAt,
            policy: $this->policy,
            now: $now,
        );

        $events = $notification->pullDomainEvents();
        self::assertCount(1, $events);

        $event = $events[0];
        self::assertInstanceOf(AuthorityNotificationScheduled::class, $event);
        self::assertSame(self::NOTIFICATION_ID, $event->notificationId);
        self::assertSame(self::ADMISSION_ID, $event->admissionId);
        self::assertSame(self::CLINIC_ID, $event->clinicId);
    }

    public function testMarkAsSentEmitsEvent(): void
    {
        $now          = $this->makeNow();
        $notification = AuthorityNotification::schedule(
            id: AuthorityNotificationId::fromString(self::NOTIFICATION_ID),
            admissionId: self::ADMISSION_ID,
            patientId: self::PATIENT_ID,
            clinicId: self::CLINIC_ID,
            admissionOpenedAt: $this->makeOpenedAt(),
            policy: $this->policy,
            now: $now,
        );

        $_ = $notification->pullDomainEvents(); // Clear schedule event

        $sentAt = $now->modify('+1 hour');
        $notification->markAsSent($sentAt);

        self::assertSame(AuthorityNotificationStatus::Sent, $notification->status());

        $events = $notification->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(AuthorityNotificationSent::class, $events[0]);

        $event = $events[0];
        self::assertInstanceOf(AuthorityNotificationSent::class, $event);
        self::assertSame(self::NOTIFICATION_ID, $event->notificationId);
    }

    public function testCancelEmitsEvent(): void
    {
        $now          = $this->makeNow();
        $notification = AuthorityNotification::schedule(
            id: AuthorityNotificationId::fromString(self::NOTIFICATION_ID),
            admissionId: self::ADMISSION_ID,
            patientId: self::PATIENT_ID,
            clinicId: self::CLINIC_ID,
            admissionOpenedAt: $this->makeOpenedAt(),
            policy: $this->policy,
            now: $now,
        );

        $_ = $notification->pullDomainEvents(); // Clear schedule event

        $reason = 'owner_found';
        $notification->cancel($reason, $now);

        self::assertSame(AuthorityNotificationStatus::Cancelled, $notification->status());

        $events = $notification->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(AuthorityNotificationCancelled::class, $events[0]);

        $event = $events[0];
        self::assertInstanceOf(AuthorityNotificationCancelled::class, $event);
        self::assertSame(self::NOTIFICATION_ID, $event->notificationId);
        self::assertSame($reason, $event->reason);
    }

    public function testReconstituteFromPersistenceDoesNotRecordEvents(): void
    {
        $now = $this->makeNow();

        $notification = AuthorityNotification::reconstituteFromPersistence(
            id: AuthorityNotificationId::fromString(self::NOTIFICATION_ID),
            admissionId: self::ADMISSION_ID,
            patientId: self::PATIENT_ID,
            clinicId: self::CLINIC_ID,
            status: AuthorityNotificationStatus::Pending,
            deadline: $now->modify('+48 hours'),
            version: 3,
            createdAt: $now,
            updatedAt: $now,
        );

        self::assertSame(3, $notification->version());
        self::assertCount(0, $notification->pullDomainEvents());
    }

    private function makeNow(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-04-01T10:00:00+00:00');
    }

    private function makeOpenedAt(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-04-01T08:00:00+00:00');
    }
}
