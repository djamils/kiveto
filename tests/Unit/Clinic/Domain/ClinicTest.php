<?php

declare(strict_types=1);

namespace App\Tests\Unit\Clinic\Domain;

use App\Clinic\Domain\Clinic;
use App\Clinic\Domain\Event\ClinicActivated;
use App\Clinic\Domain\Event\ClinicClosed;
use App\Clinic\Domain\Event\ClinicCreated;
use App\Clinic\Domain\Event\ClinicLocaleChanged;
use App\Clinic\Domain\Event\ClinicRenamed;
use App\Clinic\Domain\Event\ClinicSlugChanged;
use App\Clinic\Domain\Event\ClinicSuspended;
use App\Clinic\Domain\Event\ClinicTimeZoneChanged;
use App\Clinic\Domain\ValueObject\ClinicId;
use App\Clinic\Domain\ValueObject\ClinicSlug;
use App\Clinic\Domain\ValueObject\ClinicStatus;
use App\Shared\Domain\Localization\Locale;
use App\Shared\Domain\Localization\TimeZone;
use PHPUnit\Framework\TestCase;

final class ClinicTest extends TestCase
{
    public function testCreateRecordsDomainEvent(): void
    {
        $clinicId = ClinicId::fromString('11111111-1111-1111-1111-111111111111');
        $now      = new \DateTimeImmutable('2025-01-01T10:00:00+00:00');

        $clinic = Clinic::create(
            id: $clinicId,
            name: 'Test Clinic',
            slug: ClinicSlug::fromString('test-clinic'),
            timeZone: TimeZone::fromString('Europe/Paris'),
            locale: Locale::fromString('fr-FR'),
            createdAt: $now,
        );

        $events = $clinic->recordedDomainEvents();
        self::assertCount(1, $events);

        $event = $events[0];
        self::assertInstanceOf(ClinicCreated::class, $event);
        self::assertSame('clinic.clinic.created.v1', $event->type());
        self::assertSame('11111111-1111-1111-1111-111111111111', $event->aggregateId());
        self::assertSame(ClinicStatus::ACTIVE, $clinic->status());
    }

    public function testCreateRejectsEmptyName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Clinic name cannot be empty');

        Clinic::create(
            id: ClinicId::fromString('11111111-1111-1111-1111-111111111111'),
            name: '',
            slug: ClinicSlug::fromString('test'),
            timeZone: TimeZone::fromString('Europe/Paris'),
            locale: Locale::fromString('fr-FR'),
            createdAt: new \DateTimeImmutable(),
        );
    }

    public function testRenameRecordsDomainEvent(): void
    {
        $clinic = $this->createClinic();
        $now    = new \DateTimeImmutable('2025-01-02T10:00:00+00:00');

        $clinic->rename('New Name', $now);

        $events = $clinic->recordedDomainEvents();
        self::assertCount(2, $events); // Create + Rename

        $event = $events[1];
        self::assertInstanceOf(ClinicRenamed::class, $event);
        self::assertSame('New Name', $clinic->name());
    }

    public function testChangeSlugRecordsDomainEvent(): void
    {
        $clinic = $this->createClinic();
        $now    = new \DateTimeImmutable('2025-01-02T10:00:00+00:00');

        $clinic->changeSlug(ClinicSlug::fromString('new-slug'), $now);

        $events = $clinic->recordedDomainEvents();
        self::assertCount(2, $events);

        $event = $events[1];
        self::assertInstanceOf(ClinicSlugChanged::class, $event);
        self::assertSame('new-slug', $clinic->slug()->toString());
    }

    public function testChangeTimeZoneRecordsDomainEvent(): void
    {
        $clinic = $this->createClinic();
        $now    = new \DateTimeImmutable('2025-01-02T10:00:00+00:00');

        $clinic->changeTimeZone(TimeZone::fromString('America/New_York'), $now);

        $events = $clinic->recordedDomainEvents();
        self::assertCount(2, $events);

        $event = $events[1];
        self::assertInstanceOf(ClinicTimeZoneChanged::class, $event);
        self::assertSame('America/New_York', $clinic->timeZone()->toString());
    }

    public function testChangeLocaleRecordsDomainEvent(): void
    {
        $clinic = $this->createClinic();
        $now    = new \DateTimeImmutable('2025-01-02T10:00:00+00:00');

        $clinic->changeLocale(Locale::fromString('en-US'), $now);

        $events = $clinic->recordedDomainEvents();
        self::assertCount(2, $events);

        $event = $events[1];
        self::assertInstanceOf(ClinicLocaleChanged::class, $event);
        self::assertSame('en-US', $clinic->locale()->toString());
    }

    public function testSuspendRecordsDomainEvent(): void
    {
        $clinic = $this->createClinic();
        $now    = new \DateTimeImmutable('2025-01-02T10:00:00+00:00');

        $clinic->suspend($now);

        $events = $clinic->recordedDomainEvents();
        self::assertCount(2, $events);

        $event = $events[1];
        self::assertInstanceOf(ClinicSuspended::class, $event);
        self::assertSame(ClinicStatus::SUSPENDED, $clinic->status());
    }

    public function testActivateRecordsDomainEvent(): void
    {
        $clinic = $this->createClinic();
        $clinic->suspend(new \DateTimeImmutable('2025-01-02T10:00:00+00:00'));
        $now = new \DateTimeImmutable('2025-01-03T10:00:00+00:00');

        $clinic->activate($now);

        $events = $clinic->recordedDomainEvents();
        self::assertCount(3, $events); // Create + Suspend + Activate

        $event = $events[2];
        self::assertInstanceOf(ClinicActivated::class, $event);
        self::assertSame(ClinicStatus::ACTIVE, $clinic->status());
    }

    public function testCloseRecordsDomainEvent(): void
    {
        $clinic = $this->createClinic();
        $now    = new \DateTimeImmutable('2025-01-02T10:00:00+00:00');

        $clinic->close($now);

        $events = $clinic->recordedDomainEvents();
        self::assertCount(2, $events);

        $event = $events[1];
        self::assertInstanceOf(ClinicClosed::class, $event);
        self::assertSame(ClinicStatus::CLOSED, $clinic->status());
    }

    public function testCannotActivateClosedClinic(): void
    {
        $clinic = $this->createClinic();
        $clinic->close(new \DateTimeImmutable('2025-01-02T10:00:00+00:00'));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot activate a closed clinic');

        $clinic->activate(new \DateTimeImmutable('2025-01-03T10:00:00+00:00'));
    }

    public function testReconstituteDoesNotRecordEvents(): void
    {
        $clinic = Clinic::reconstitute(
            id: ClinicId::fromString('11111111-1111-1111-1111-111111111111'),
            name: 'Test Clinic',
            slug: ClinicSlug::fromString('test-clinic'),
            timeZone: TimeZone::fromString('Europe/Paris'),
            locale: Locale::fromString('fr-FR'),
            status: ClinicStatus::ACTIVE,
            createdAt: new \DateTimeImmutable('2025-01-01T10:00:00+00:00'),
            updatedAt: new \DateTimeImmutable('2025-01-01T10:00:00+00:00'),
        );

        self::assertSame([], $clinic->recordedDomainEvents());
    }

    private function createClinic(): Clinic
    {
        return Clinic::create(
            id: ClinicId::fromString('11111111-1111-1111-1111-111111111111'),
            name: 'Test Clinic',
            slug: ClinicSlug::fromString('test-clinic'),
            timeZone: TimeZone::fromString('Europe/Paris'),
            locale: Locale::fromString('fr-FR'),
            createdAt: new \DateTimeImmutable('2025-01-01T10:00:00+00:00'),
        );
    }
}
