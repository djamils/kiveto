<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Regulatory;

use App\Context\Regulatory\Domain\Event\StrayCustodyBegun;
use App\Context\Regulatory\Domain\Event\StrayCustodyCancelledOwnerFound;
use App\Context\Regulatory\Domain\Event\StrayCustodyClosedHandedToAuthority;
use App\Context\Regulatory\Domain\Policy\RegulatoryPolicyInterface;
use App\Context\Regulatory\Domain\StrayCustody;
use App\Context\Regulatory\Domain\ValueObject\StrayCustodyId;
use App\Context\Regulatory\Domain\ValueObject\StrayCustodyStatus;
use PHPUnit\Framework\TestCase;

final class StrayCustodyTest extends TestCase
{
    private const string CUSTODY_ID   = '55555555-5555-5555-5555-555555555555';
    private const string ADMISSION_ID = '22222222-2222-2222-2222-222222222222';
    private const string PATIENT_ID   = '33333333-3333-3333-3333-333333333333';
    private const string CLINIC_ID    = '44444444-4444-4444-4444-444444444444';

    private RegulatoryPolicyInterface $policy;

    protected function setUp(): void
    {
        $this->policy = $this->createStub(RegulatoryPolicyInterface::class);
        $this->policy->method('getStrayCustodyDeadline')
            ->willReturnCallback(static fn (\DateTimeImmutable $date): \DateTimeImmutable => $date->modify('+15 days'))
        ;
        $this->policy->method('getAuthorityNotificationDeadline')
            ->willReturnCallback(static fn (\DateTimeImmutable $date): \DateTimeImmutable => $date->modify('+2 days'))
        ;
    }

    public function testBeginCreatesWithActiveStatus(): void
    {
        $now     = $this->makeNow();
        $custody = $this->makeBegunCustody();

        self::assertSame(StrayCustodyStatus::Active, $custody->status());
        self::assertSame(self::ADMISSION_ID, $custody->admissionId());
        self::assertSame(self::PATIENT_ID, $custody->patientId());
        self::assertSame(self::CLINIC_ID, $custody->clinicId());
        self::assertSame(1, $custody->version());
        // Stub returns +15 days from admission: 2026-04-30 + 15 = 2026-05-15
        self::assertSame('2026-05-15', $custody->deadline()->format('Y-m-d'));

        $events = $custody->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(StrayCustodyBegun::class, $events[0]);
    }

    public function testBeginEmitsStrayCustodyBegunEvent(): void
    {
        $custody = $this->makeBegunCustody();

        $events = $custody->pullDomainEvents();
        self::assertCount(1, $events);

        $event = $events[0];
        self::assertInstanceOf(StrayCustodyBegun::class, $event);
        self::assertSame(self::CUSTODY_ID, $event->custodyId);
        self::assertSame(self::ADMISSION_ID, $event->admissionId);
        self::assertSame(self::CLINIC_ID, $event->clinicId);
    }

    public function testCancelOwnerFoundChangesStatus(): void
    {
        $custody = $this->makeBegunCustody();
        $_       = $custody->pullDomainEvents(); // Clear begin event

        $now = $this->makeNow()->modify('+1 day');
        $custody->cancelOwnerFound($now);

        self::assertSame(StrayCustodyStatus::CancelledOwnerFound, $custody->status());

        $events = $custody->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(StrayCustodyCancelledOwnerFound::class, $events[0]);

        $event = $events[0];
        self::assertInstanceOf(StrayCustodyCancelledOwnerFound::class, $event);
        self::assertSame(self::CUSTODY_ID, $event->custodyId);
        self::assertSame(self::ADMISSION_ID, $event->admissionId);
    }

    public function testCloseHandedToAuthorityChangesStatus(): void
    {
        $custody = $this->makeBegunCustody();
        $_       = $custody->pullDomainEvents(); // Clear begin event

        $now = $this->makeNow()->modify('+8 days');
        $custody->closeHandedToAuthority($now);

        self::assertSame(StrayCustodyStatus::ClosedHandedToAuthority, $custody->status());

        $events = $custody->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(StrayCustodyClosedHandedToAuthority::class, $events[0]);

        $event = $events[0];
        self::assertInstanceOf(StrayCustodyClosedHandedToAuthority::class, $event);
        self::assertSame(self::CUSTODY_ID, $event->custodyId);
        self::assertSame(self::ADMISSION_ID, $event->admissionId);
    }

    public function testCancelOwnerFoundThrowsIfAlreadyClosed(): void
    {
        $custody = $this->makeBegunCustody();

        $now = $this->makeNow()->modify('+8 days');
        $custody->closeHandedToAuthority($now);

        $this->expectException(\DomainException::class);

        $custody->cancelOwnerFound($now);
    }

    public function testCloseHandedToAuthorityThrowsIfAlreadyCancelled(): void
    {
        $custody = $this->makeBegunCustody();

        $now = $this->makeNow()->modify('+1 day');
        $custody->cancelOwnerFound($now);

        $this->expectException(\DomainException::class);

        $custody->closeHandedToAuthority($now);
    }

    public function testReconstituteFromPersistenceDoesNotRecordEvents(): void
    {
        $now = $this->makeNow();

        $custody = StrayCustody::reconstituteFromPersistence(
            id: StrayCustodyId::fromString(self::CUSTODY_ID),
            admissionId: self::ADMISSION_ID,
            patientId: self::PATIENT_ID,
            clinicId: self::CLINIC_ID,
            status: StrayCustodyStatus::Active,
            deadline: $now->modify('+12 days'),
            version: 2,
            createdAt: $now,
            updatedAt: $now,
        );

        self::assertSame(2, $custody->version());
        self::assertCount(0, $custody->pullDomainEvents());
    }

    private function makeNow(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-04-30T10:00:00+00:00');
    }

    private function makeBegunCustody(): StrayCustody
    {
        return StrayCustody::begin(
            id: StrayCustodyId::fromString(self::CUSTODY_ID),
            admissionId: self::ADMISSION_ID,
            patientId: self::PATIENT_ID,
            clinicId: self::CLINIC_ID,
            admissionOpenedAt: $this->makeNow(),
            policy: $this->policy,
            now: $this->makeNow(),
        );
    }
}
