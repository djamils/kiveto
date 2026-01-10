<?php

declare(strict_types=1);

namespace App\Tests\Unit\Clinic\Domain;

use App\Clinic\Domain\ClinicGroup;
use App\Clinic\Domain\Event\ClinicGroupActivated;
use App\Clinic\Domain\Event\ClinicGroupCreated;
use App\Clinic\Domain\Event\ClinicGroupRenamed;
use App\Clinic\Domain\Event\ClinicGroupSuspended;
use App\Clinic\Domain\ValueObject\ClinicGroupId;
use App\Clinic\Domain\ValueObject\ClinicGroupStatus;
use PHPUnit\Framework\TestCase;

final class ClinicGroupTest extends TestCase
{
    public function testCreateRecordsDomainEvent(): void
    {
        $groupId = ClinicGroupId::fromString('11111111-1111-1111-1111-111111111111');
        $now     = new \DateTimeImmutable('2025-01-01T10:00:00+00:00');

        $group = ClinicGroup::create(
            id: $groupId,
            name: 'Test Group',
            createdAt: $now,
        );

        $events = $group->recordedDomainEvents();
        self::assertCount(1, $events);

        $event = $events[0];
        self::assertInstanceOf(ClinicGroupCreated::class, $event);
        self::assertSame('clinic.clinic-group.created.v1', $event->type());
        self::assertSame(ClinicGroupStatus::ACTIVE, $group->status());
    }

    public function testCreateRejectsEmptyName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Clinic group name cannot be empty');

        ClinicGroup::create(
            id: ClinicGroupId::fromString('11111111-1111-1111-1111-111111111111'),
            name: '',
            createdAt: new \DateTimeImmutable(),
        );
    }

    public function testRenameRecordsDomainEvent(): void
    {
        $group = $this->createGroup();

        $group->rename('New Name');

        $events = $group->recordedDomainEvents();
        self::assertCount(2, $events);

        $event = $events[1];
        self::assertInstanceOf(ClinicGroupRenamed::class, $event);
        self::assertSame('New Name', $group->name());
    }

    public function testSuspendRecordsDomainEvent(): void
    {
        $group = $this->createGroup();

        $group->suspend();

        $events = $group->recordedDomainEvents();
        self::assertCount(2, $events);

        $event = $events[1];
        self::assertInstanceOf(ClinicGroupSuspended::class, $event);
        self::assertSame(ClinicGroupStatus::SUSPENDED, $group->status());
    }

    public function testActivateRecordsDomainEvent(): void
    {
        $group = $this->createGroup();
        $group->suspend();

        $group->activate();

        $events = $group->recordedDomainEvents();
        self::assertCount(3, $events);

        $event = $events[2];
        self::assertInstanceOf(ClinicGroupActivated::class, $event);
        self::assertSame(ClinicGroupStatus::ACTIVE, $group->status());
    }

    public function testReconstituteDoesNotRecordEvents(): void
    {
        $group = ClinicGroup::reconstitute(
            id: ClinicGroupId::fromString('11111111-1111-1111-1111-111111111111'),
            name: 'Test Group',
            status: ClinicGroupStatus::ACTIVE,
            createdAt: new \DateTimeImmutable('2025-01-01T10:00:00+00:00'),
        );

        self::assertSame([], $group->recordedDomainEvents());
    }

    private function createGroup(): ClinicGroup
    {
        return ClinicGroup::create(
            id: ClinicGroupId::fromString('11111111-1111-1111-1111-111111111111'),
            name: 'Test Group',
            createdAt: new \DateTimeImmutable('2025-01-01T10:00:00+00:00'),
        );
    }
}
