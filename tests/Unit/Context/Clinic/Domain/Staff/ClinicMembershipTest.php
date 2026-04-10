<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Domain\Staff;

use App\Context\Clinic\Domain\Staff\ClinicMembership;
use App\Context\Clinic\Domain\Staff\Event\ClinicMembershipCreated;
use App\Context\Clinic\Domain\Staff\Event\ClinicMembershipDefaultChanged;
use App\Context\Clinic\Domain\Staff\Event\ClinicMembershipDisabled;
use App\Context\Clinic\Domain\Staff\Event\ClinicMembershipEnabled;
use App\Context\Clinic\Domain\Staff\Event\ClinicMembershipEngagementChanged;
use App\Context\Clinic\Domain\Staff\Event\ClinicMembershipRoleChanged;
use App\Context\Clinic\Domain\Staff\Event\ClinicMembershipValidityChanged;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipEngagement;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipId;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipStatus;
use App\Context\Clinic\Domain\Staff\ValueObject\UserId;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use PHPUnit\Framework\TestCase;

final class ClinicMembershipTest extends TestCase
{
    private const string ID        = '01912345-6789-7abc-8def-000000000001';
    private const string CLINIC_ID = '01912345-6789-7abc-8def-000000000002';
    private const string USER_ID   = '01912345-6789-7abc-8def-000000000003';

    public function testCreateRecordsEventAndSetsActiveStatus(): void
    {
        $membership = $this->createMembership();

        self::assertSame(self::ID, $membership->id()->toString());
        self::assertSame(self::CLINIC_ID, $membership->clinicId()->toString());
        self::assertSame(self::USER_ID, $membership->userId()->toString());
        self::assertSame(ClinicMemberRole::VETERINARY, $membership->role());
        self::assertSame(ClinicMembershipEngagement::EMPLOYEE, $membership->engagement());
        self::assertSame(ClinicMembershipStatus::ACTIVE, $membership->status());

        $events = $membership->recordedDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ClinicMembershipCreated::class, $events[0]);
    }

    public function testCreateThrowsWhenValidFromAfterValidUntil(): void
    {
        $this->expectException(\DomainException::class);

        ClinicMembership::create(
            id: ClinicMembershipId::fromString(self::ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            userId: UserId::fromString(self::USER_ID),
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            validFrom: new \DateTimeImmutable('2026-12-31'),
            validUntil: new \DateTimeImmutable('2026-01-01'),
            createdAt: new \DateTimeImmutable('2026-01-01'),
        );
    }

    public function testDisableRecordsEvent(): void
    {
        $membership = $this->createMembership();
        $_          = $membership->pullDomainEvents();

        $membership->disable();

        self::assertSame(ClinicMembershipStatus::DISABLED, $membership->status());
        $events = $membership->recordedDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ClinicMembershipDisabled::class, $events[0]);
    }

    public function testDisableIsIdempotent(): void
    {
        $membership = $this->reconstitute(ClinicMembershipStatus::DISABLED);

        $membership->disable();

        self::assertSame([], $membership->recordedDomainEvents());
    }

    public function testEnableRecordsEventWithRole(): void
    {
        $membership = $this->reconstitute(ClinicMembershipStatus::DISABLED);

        $membership->enable();

        self::assertSame(ClinicMembershipStatus::ACTIVE, $membership->status());
        $events = $membership->recordedDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ClinicMembershipEnabled::class, $events[0]);
        self::assertSame('VETERINARY', $events[0]->payload()['role']);
    }

    public function testEnableIsIdempotent(): void
    {
        $membership = $this->reconstitute(ClinicMembershipStatus::ACTIVE);

        $membership->enable();

        self::assertSame([], $membership->recordedDomainEvents());
    }

    public function testChangeRoleRecordsEvent(): void
    {
        $membership = $this->reconstitute();
        $membership->changeRole(ClinicMemberRole::MANAGER);

        self::assertSame(ClinicMemberRole::MANAGER, $membership->role());
        $events = $membership->recordedDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ClinicMembershipRoleChanged::class, $events[0]);
        self::assertSame('MANAGER', $events[0]->payload()['newRole']);
    }

    public function testChangeRoleIsIdempotent(): void
    {
        $membership = $this->reconstitute();
        $membership->changeRole(ClinicMemberRole::VETERINARY);

        self::assertSame([], $membership->recordedDomainEvents());
    }

    public function testChangeEngagementRecordsEvent(): void
    {
        $membership = $this->reconstitute();
        $membership->changeEngagement(ClinicMembershipEngagement::CONTRACTOR);

        self::assertSame(ClinicMembershipEngagement::CONTRACTOR, $membership->engagement());
        $events = $membership->recordedDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ClinicMembershipEngagementChanged::class, $events[0]);
    }

    public function testChangeEngagementIsIdempotent(): void
    {
        $membership = $this->reconstitute();
        $membership->changeEngagement(ClinicMembershipEngagement::EMPLOYEE);

        self::assertSame([], $membership->recordedDomainEvents());
    }

    public function testChangeValidityWindowRecordsEvent(): void
    {
        $membership = $this->reconstitute();
        $newFrom    = new \DateTimeImmutable('2026-06-01');
        $newUntil   = new \DateTimeImmutable('2026-12-31');

        $membership->changeValidityWindow($newFrom, $newUntil);

        self::assertSame($newFrom, $membership->validFrom());
        self::assertSame($newUntil, $membership->validUntil());
        $events = $membership->recordedDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ClinicMembershipValidityChanged::class, $events[0]);
    }

    public function testChangeValidityWindowIsIdempotent(): void
    {
        $validFrom  = new \DateTimeImmutable('2026-01-01');
        $membership = $this->reconstitute(validFrom: $validFrom);

        $membership->changeValidityWindow($validFrom, null);

        self::assertSame([], $membership->recordedDomainEvents());
    }

    public function testChangeValidityWindowThrowsOnInvalidWindow(): void
    {
        $membership = $this->reconstitute();

        $this->expectException(\DomainException::class);
        $membership->changeValidityWindow(
            new \DateTimeImmutable('2026-12-31'),
            new \DateTimeImmutable('2026-01-01'),
        );
    }

    public function testIsEffectiveAtReturnsFalseWhenDisabled(): void
    {
        $membership = $this->reconstitute(ClinicMembershipStatus::DISABLED);

        self::assertFalse($membership->isEffectiveAt(new \DateTimeImmutable('2026-06-01')));
    }

    public function testIsEffectiveAtReturnsFalseBeforeValidFrom(): void
    {
        $membership = $this->reconstitute(validFrom: new \DateTimeImmutable('2026-06-01'));

        self::assertFalse($membership->isEffectiveAt(new \DateTimeImmutable('2026-01-01')));
    }

    public function testIsEffectiveAtReturnsFalseAfterValidUntil(): void
    {
        $membership = $this->reconstitute(
            validFrom: new \DateTimeImmutable('2026-01-01'),
            validUntil: new \DateTimeImmutable('2026-06-01'),
        );

        self::assertFalse($membership->isEffectiveAt(new \DateTimeImmutable('2026-12-01')));
    }

    public function testIsEffectiveAtReturnsTrueWithinWindow(): void
    {
        $membership = $this->reconstitute(validFrom: new \DateTimeImmutable('2026-01-01'));

        self::assertTrue($membership->isEffectiveAt(new \DateTimeImmutable('2026-06-01')));
    }

    public function testReconstitutePreservesAllProperties(): void
    {
        $validFrom  = new \DateTimeImmutable('2026-01-01');
        $validUntil = new \DateTimeImmutable('2026-12-31');
        $createdAt  = new \DateTimeImmutable('2026-01-01');

        $membership = ClinicMembership::reconstitute(
            id: ClinicMembershipId::fromString(self::ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            userId: UserId::fromString(self::USER_ID),
            role: ClinicMemberRole::RECEPTIONIST,
            engagement: ClinicMembershipEngagement::CONTRACTOR,
            status: ClinicMembershipStatus::DISABLED,
            validFrom: $validFrom,
            validUntil: $validUntil,
            createdAt: $createdAt,
            isDefault: false,
        );

        self::assertSame(self::ID, $membership->id()->toString());
        self::assertSame(ClinicMemberRole::RECEPTIONIST, $membership->role());
        self::assertSame(ClinicMembershipEngagement::CONTRACTOR, $membership->engagement());
        self::assertSame(ClinicMembershipStatus::DISABLED, $membership->status());
        self::assertSame($validFrom, $membership->validFrom());
        self::assertSame($validUntil, $membership->validUntil());
        self::assertSame($createdAt, $membership->createdAt());
        self::assertSame([], $membership->recordedDomainEvents());
    }

    public function testCreateSetsIsDefaultToFalse(): void
    {
        $membership = $this->createMembership();

        self::assertFalse($membership->isDefault());
    }

    public function testSetAsDefaultRecordsEvent(): void
    {
        $membership = $this->createMembership();
        $_          = $membership->pullDomainEvents();

        $membership->setAsDefault();

        self::assertTrue($membership->isDefault());
        $events = $membership->recordedDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ClinicMembershipDefaultChanged::class, $events[0]);
        $expectedPayload = [
            'membershipId' => self::ID,
            'clinicId'     => self::CLINIC_ID,
            'userId'       => self::USER_ID,
            'isDefault'    => true,
        ];
        self::assertSame($expectedPayload, $events[0]->payload());
    }

    public function testSetAsDefaultIsIdempotent(): void
    {
        $membership = $this->createMembership();
        $_          = $membership->pullDomainEvents();

        $membership->setAsDefault();
        $membership->setAsDefault();

        $events = $membership->recordedDomainEvents();
        self::assertCount(1, $events);
    }

    public function testClearDefaultRecordsEvent(): void
    {
        $membership = $this->createMembership();
        $membership->setAsDefault();
        $_ = $membership->pullDomainEvents();

        $membership->clearDefault();

        self::assertFalse($membership->isDefault());
        $events = $membership->recordedDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ClinicMembershipDefaultChanged::class, $events[0]);
        $expectedPayload = [
            'membershipId' => self::ID,
            'clinicId'     => self::CLINIC_ID,
            'userId'       => self::USER_ID,
            'isDefault'    => false,
        ];
        self::assertSame($expectedPayload, $events[0]->payload());
    }

    public function testClearDefaultIsIdempotent(): void
    {
        $membership = $this->createMembership();
        $_          = $membership->pullDomainEvents();

        $membership->clearDefault();

        self::assertSame([], $membership->recordedDomainEvents());
    }

    public function testReconstituteWithIsDefaultTrue(): void
    {
        $membership = ClinicMembership::reconstitute(
            id: ClinicMembershipId::fromString(self::ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            userId: UserId::fromString(self::USER_ID),
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            status: ClinicMembershipStatus::ACTIVE,
            validFrom: new \DateTimeImmutable('2026-01-01'),
            validUntil: null,
            createdAt: new \DateTimeImmutable('2026-01-01'),
            isDefault: true,
        );

        self::assertTrue($membership->isDefault());
    }

    public function testReconstituteWithIsDefaultTrueThenClearDefaultRecordsEvent(): void
    {
        $membership = ClinicMembership::reconstitute(
            id: ClinicMembershipId::fromString(self::ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            userId: UserId::fromString(self::USER_ID),
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            status: ClinicMembershipStatus::ACTIVE,
            validFrom: new \DateTimeImmutable('2026-01-01'),
            validUntil: null,
            createdAt: new \DateTimeImmutable('2026-01-01'),
            isDefault: true,
        );

        $membership->clearDefault();

        self::assertFalse($membership->isDefault());
        $events = $membership->recordedDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ClinicMembershipDefaultChanged::class, $events[0]);
        $expectedPayload = [
            'membershipId' => self::ID,
            'clinicId'     => self::CLINIC_ID,
            'userId'       => self::USER_ID,
            'isDefault'    => false,
        ];
        self::assertSame($expectedPayload, $events[0]->payload());
    }

    private function createMembership(): ClinicMembership
    {
        return ClinicMembership::create(
            id: ClinicMembershipId::fromString(self::ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            userId: UserId::fromString(self::USER_ID),
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            validFrom: new \DateTimeImmutable('2026-01-01'),
            validUntil: null,
            createdAt: new \DateTimeImmutable('2026-01-01'),
        );
    }

    private function reconstitute(
        ClinicMembershipStatus $status = ClinicMembershipStatus::ACTIVE,
        ?\DateTimeImmutable $validFrom = null,
        ?\DateTimeImmutable $validUntil = null,
    ): ClinicMembership {
        return ClinicMembership::reconstitute(
            id: ClinicMembershipId::fromString(self::ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            userId: UserId::fromString(self::USER_ID),
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            status: $status,
            validFrom: $validFrom ?? new \DateTimeImmutable('2026-01-01'),
            validUntil: $validUntil,
            createdAt: new \DateTimeImmutable('2026-01-01'),
            isDefault: false,
        );
    }
}
