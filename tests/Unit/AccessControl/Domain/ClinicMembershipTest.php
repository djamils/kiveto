<?php

declare(strict_types=1);

namespace App\Tests\Unit\AccessControl\Domain;

use App\AccessControl\Domain\ClinicMembership;
use App\AccessControl\Domain\Event\ClinicMembershipCreated;
use App\AccessControl\Domain\Event\ClinicMembershipDisabled;
use App\AccessControl\Domain\Event\ClinicMembershipEnabled;
use App\AccessControl\Domain\Event\ClinicMembershipEngagementChanged;
use App\AccessControl\Domain\Event\ClinicMembershipRoleChanged;
use App\AccessControl\Domain\Event\ClinicMembershipValidityChanged;
use App\AccessControl\Domain\ValueObject\ClinicMemberRole;
use App\AccessControl\Domain\ValueObject\ClinicMembershipEngagement;
use App\AccessControl\Domain\ValueObject\ClinicMembershipStatus;
use App\AccessControl\Domain\ValueObject\MembershipId;
use App\Clinic\Domain\ValueObject\ClinicId;
use App\IdentityAccess\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

final class ClinicMembershipTest extends TestCase
{
    public function testCreateMembershipWithValidData(): void
    {
        $membershipId = MembershipId::fromString('01234567-89ab-cdef-0123-456789abcdef');
        $clinicId     = ClinicId::fromString('11111111-1111-1111-1111-111111111111');
        $userId       = UserId::fromString('22222222-2222-2222-2222-222222222222');
        $validFrom    = new \DateTimeImmutable('2025-01-01 00:00:00');
        $validUntil   = new \DateTimeImmutable('2025-12-31 23:59:59');
        $createdAt    = new \DateTimeImmutable('2025-01-01 00:00:00');

        $membership = ClinicMembership::create(
            id: $membershipId,
            clinicId: $clinicId,
            userId: $userId,
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::CONTRACTOR,
            validFrom: $validFrom,
            validUntil: $validUntil,
            createdAt: $createdAt,
        );

        self::assertTrue($membership->id()->equals($membershipId));
        self::assertTrue($membership->clinicId()->equals($clinicId));
        self::assertTrue($membership->userId()->equals($userId));
        self::assertSame(ClinicMemberRole::VETERINARY, $membership->role());
        self::assertSame(ClinicMembershipEngagement::CONTRACTOR, $membership->engagement());
        self::assertSame(ClinicMembershipStatus::ACTIVE, $membership->status());
        self::assertSame($validFrom, $membership->validFrom());
        self::assertSame($validUntil, $membership->validUntil());

        $events = $membership->recordedDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ClinicMembershipCreated::class, $events[0]);
    }

    public function testCreateMembershipFailsWhenValidFromAfterValidUntil(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('validFrom must be before or equal to validUntil.');

        ClinicMembership::create(
            id: MembershipId::fromString('01234567-89ab-cdef-0123-456789abcdef'),
            clinicId: ClinicId::fromString('11111111-1111-1111-1111-111111111111'),
            userId: UserId::fromString('22222222-2222-2222-2222-222222222222'),
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            validFrom: new \DateTimeImmutable('2025-12-31'),
            validUntil: new \DateTimeImmutable('2025-01-01'),
            createdAt: new \DateTimeImmutable('2025-01-01'),
        );
    }

    public function testDisableMembership(): void
    {
        $membership = $this->createSampleMembership();

        $membership->disable();

        self::assertSame(ClinicMembershipStatus::DISABLED, $membership->status());

        $events = $membership->recordedDomainEvents();
        self::assertCount(2, $events); // created + disabled
        self::assertInstanceOf(ClinicMembershipDisabled::class, $events[1]);
    }

    public function testDisableAlreadyDisabledMembershipDoesNothing(): void
    {
        $membership = $this->createSampleMembership();
        $membership->disable();
        $pulledEvents = $membership->pullDomainEvents(); // clear events
        unset($pulledEvents);

        $membership->disable();

        $events = $membership->recordedDomainEvents();
        self::assertCount(0, $events); // no new event
    }

    public function testEnableMembership(): void
    {
        $membership = $this->createSampleMembership();
        $membership->disable();
        $pulledEvents = $membership->pullDomainEvents(); // clear previous events
        unset($pulledEvents);

        $membership->enable();

        self::assertSame(ClinicMembershipStatus::ACTIVE, $membership->status());

        $events = $membership->recordedDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ClinicMembershipEnabled::class, $events[0]);
    }

    public function testChangeRole(): void
    {
        $membership   = $this->createSampleMembership();
        $pulledEvents = $membership->pullDomainEvents(); // clear created event
        unset($pulledEvents);

        $membership->changeRole(ClinicMemberRole::CLINIC_ADMIN);

        self::assertSame(ClinicMemberRole::CLINIC_ADMIN, $membership->role());

        $events = $membership->recordedDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ClinicMembershipRoleChanged::class, $events[0]);
    }

    public function testChangeRoleToSameDoesNothing(): void
    {
        $membership   = $this->createSampleMembership();
        $pulledEvents = $membership->pullDomainEvents();
        unset($pulledEvents);

        $membership->changeRole(ClinicMemberRole::VETERINARY);

        $events = $membership->recordedDomainEvents();
        self::assertCount(0, $events);
    }

    public function testChangeEngagement(): void
    {
        $membership   = $this->createSampleMembership();
        $pulledEvents = $membership->pullDomainEvents();
        unset($pulledEvents);

        $membership->changeEngagement(ClinicMembershipEngagement::EMPLOYEE);

        self::assertSame(ClinicMembershipEngagement::EMPLOYEE, $membership->engagement());

        $events = $membership->recordedDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ClinicMembershipEngagementChanged::class, $events[0]);
    }

    public function testChangeValidity(): void
    {
        $membership   = $this->createSampleMembership();
        $pulledEvents = $membership->pullDomainEvents();
        unset($pulledEvents);

        $newValidFrom  = new \DateTimeImmutable('2026-01-01');
        $newValidUntil = new \DateTimeImmutable('2026-12-31');

        $membership->changeValidity($newValidFrom, $newValidUntil);

        self::assertSame($newValidFrom, $membership->validFrom());
        self::assertSame($newValidUntil, $membership->validUntil());

        $events = $membership->recordedDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ClinicMembershipValidityChanged::class, $events[0]);
    }

    public function testChangeValidityFailsWhenInvalidWindow(): void
    {
        $this->expectException(\DomainException::class);

        $membership = $this->createSampleMembership();

        $membership->changeValidity(
            new \DateTimeImmutable('2026-12-31'),
            new \DateTimeImmutable('2026-01-01'),
        );
    }

    public function testIsEffectiveAtReturnsTrueWhenActiveAndInValidityWindow(): void
    {
        $membership = ClinicMembership::create(
            id: MembershipId::fromString('01234567-89ab-cdef-0123-456789abcdef'),
            clinicId: ClinicId::fromString('11111111-1111-1111-1111-111111111111'),
            userId: UserId::fromString('22222222-2222-2222-2222-222222222222'),
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            validFrom: new \DateTimeImmutable('2025-01-01'),
            validUntil: new \DateTimeImmutable('2025-12-31'),
            createdAt: new \DateTimeImmutable('2025-01-01'),
        );

        $now = new \DateTimeImmutable('2025-06-15');

        self::assertTrue($membership->isEffectiveAt($now));
    }

    public function testIsEffectiveAtReturnsFalseWhenDisabled(): void
    {
        $membership = $this->createSampleMembership();
        $membership->disable();

        $now = new \DateTimeImmutable('2025-06-15');

        self::assertFalse($membership->isEffectiveAt($now));
    }

    public function testIsEffectiveAtReturnsFalseWhenBeforeValidFrom(): void
    {
        $membership = ClinicMembership::create(
            id: MembershipId::fromString('01234567-89ab-cdef-0123-456789abcdef'),
            clinicId: ClinicId::fromString('11111111-1111-1111-1111-111111111111'),
            userId: UserId::fromString('22222222-2222-2222-2222-222222222222'),
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            validFrom: new \DateTimeImmutable('2025-06-01'),
            validUntil: null,
            createdAt: new \DateTimeImmutable('2025-01-01'),
        );

        $now = new \DateTimeImmutable('2025-05-31');

        self::assertFalse($membership->isEffectiveAt($now));
    }

    public function testIsEffectiveAtReturnsFalseWhenAfterValidUntil(): void
    {
        $membership = ClinicMembership::create(
            id: MembershipId::fromString('01234567-89ab-cdef-0123-456789abcdef'),
            clinicId: ClinicId::fromString('11111111-1111-1111-1111-111111111111'),
            userId: UserId::fromString('22222222-2222-2222-2222-222222222222'),
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::CONTRACTOR,
            validFrom: new \DateTimeImmutable('2025-01-01'),
            validUntil: new \DateTimeImmutable('2025-06-30'),
            createdAt: new \DateTimeImmutable('2025-01-01'),
        );

        $now = new \DateTimeImmutable('2025-07-01');

        self::assertFalse($membership->isEffectiveAt($now));
    }

    public function testIsEffectiveAtReturnsTrueWhenValidUntilIsNull(): void
    {
        $membership = ClinicMembership::create(
            id: MembershipId::fromString('01234567-89ab-cdef-0123-456789abcdef'),
            clinicId: ClinicId::fromString('11111111-1111-1111-1111-111111111111'),
            userId: UserId::fromString('22222222-2222-2222-2222-222222222222'),
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            validFrom: new \DateTimeImmutable('2025-01-01'),
            validUntil: null,
            createdAt: new \DateTimeImmutable('2025-01-01'),
        );

        $now = new \DateTimeImmutable('2099-12-31');

        self::assertTrue($membership->isEffectiveAt($now));
    }

    private function createSampleMembership(): ClinicMembership
    {
        return ClinicMembership::create(
            id: MembershipId::fromString('01234567-89ab-cdef-0123-456789abcdef'),
            clinicId: ClinicId::fromString('11111111-1111-1111-1111-111111111111'),
            userId: UserId::fromString('22222222-2222-2222-2222-222222222222'),
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::CONTRACTOR,
            validFrom: new \DateTimeImmutable('2025-01-01'),
            validUntil: new \DateTimeImmutable('2025-12-31'),
            createdAt: new \DateTimeImmutable('2025-01-01'),
        );
    }
}
