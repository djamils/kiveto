<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Scheduling\Infrastructure\Persistence;

use App\Context\Scheduling\Domain\Appointment;
use App\Context\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\AppointmentSource;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Context\Scheduling\Domain\ValueObject\CancellationActor;
use App\Context\Scheduling\Domain\ValueObject\CancellationReason;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PractitionerAssignee;
use App\Context\Scheduling\Domain\ValueObject\TimeSlot;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * AC 143: a pending online request persists and reloads with every field
 * the pending lifecycle added, and so do its later states.
 */
final class AppointmentRoundTripTest extends KernelTestCase
{
    private const string ID     = '01960000-0000-7000-8000-00000000a501';
    private const string CLINIC = '01960000-0000-7000-8000-00000000a5c1';
    private const string VET    = '01960000-0000-7000-8000-00000000a5d1';
    private const string MOTIF  = '01960000-0000-7000-8000-00000000a5e1';
    private const string OWNER  = '01960000-0000-7000-8000-00000000a5f1';
    private const string ANIMAL = '01960000-0000-7000-8000-00000000a5f2';

    private AppointmentRepositoryInterface $repository;

    protected function setUp(): void
    {
        $repository = self::getContainer()->get(AppointmentRepositoryInterface::class);
        \assert($repository instanceof AppointmentRepositoryInterface);
        $this->repository = $repository;
    }

    public function testAPendingRequestReloadsWithEveryField(): void
    {
        $this->repository->save($this->request());

        $loaded = $this->reload();

        self::assertSame(AppointmentStatus::PENDING_CLINIC_CONFIRMATION, $loaded->status());
        self::assertSame(AppointmentSource::PORTAL, $loaded->source());
        self::assertSame(self::MOTIF, $loaded->bookingMotifId());
        self::assertSame('2026-11-04 07:00:00', $loaded->pendingExpiresAt()?->format('Y-m-d H:i:s'));
        self::assertFalse($loaded->confirmedSilently());
        self::assertNull($loaded->cancelledBy());
        self::assertNull($loaded->cancellationReason());
        self::assertSame(self::CLINIC, $loaded->clinicId()->toString());
        self::assertSame(self::VET, $loaded->practitionerAssignee()->userId()->toString());
        self::assertSame('2026-11-04 09:00:00', $loaded->timeSlot()->startsAtUtc()->format('Y-m-d H:i:s'));
        self::assertSame(20, $loaded->timeSlot()->durationMinutes());
        self::assertSame([self::OWNER, self::ANIMAL, null], [$loaded->ownerId(), $loaded->animalId(), $loaded->linkedAdmissionId()]);
        self::assertSame(['Vaccination', 'Rappel annuel'], [$loaded->reason(), $loaded->notes()]);
        self::assertSame('2026-11-02 10:00:00', $loaded->createdAt()->format('Y-m-d H:i:s'));
        self::assertNull($loaded->serviceStartedAt());
    }

    public function testASilentConfirmationReloads(): void
    {
        $this->repository->save($this->request());

        $appointment = $this->reload();
        $appointment->confirm(silent: true);
        $appointment->complete();
        $this->repository->save($appointment);

        $loaded = $this->reload();
        self::assertSame(AppointmentStatus::COMPLETED, $loaded->status());
        self::assertTrue($loaded->confirmedSilently());
        self::assertNull($loaded->pendingExpiresAt());
    }

    public function testAClientCancellationReloadsWithItsActorAndReason(): void
    {
        $this->repository->save($this->request());

        $appointment = $this->reload();
        $appointment->cancel(CancellationActor::CLIENT, CancellationReason::UNAVAILABLE);
        $this->repository->save($appointment);

        $loaded = $this->reload();
        self::assertSame(AppointmentStatus::CANCELLED, $loaded->status());
        self::assertSame(CancellationActor::CLIENT, $loaded->cancelledBy());
        self::assertSame(CancellationReason::UNAVAILABLE, $loaded->cancellationReason());
    }

    public function testADeclinedRequestReloads(): void
    {
        $this->repository->save($this->request());

        $appointment = $this->reload();
        $appointment->cancel(CancellationActor::CLINIC);
        $this->repository->save($appointment);

        self::assertSame(AppointmentStatus::DECLINED, $this->reload()->status());
    }

    public function testInstantsHandedOverInAClinicZoneAreStoredAsUtc(): void
    {
        $paris = new \DateTimeZone('Europe/Paris');

        $this->repository->save(Appointment::request(
            id: AppointmentId::fromString(self::ID),
            clinicId: ClinicId::fromString(self::CLINIC),
            practitionerAssignee: new PractitionerAssignee(UserId::fromString(self::VET)),
            timeSlot: new TimeSlot(new \DateTimeImmutable('2026-11-04 09:00:00'), 20),
            reason: null,
            notes: null,
            createdAt: new \DateTimeImmutable('2026-11-02 11:00:00', $paris),
            ownerId: null,
            animalId: null,
            bookingMotifId: null,
            source: AppointmentSource::PORTAL,
            pendingExpiresAt: new \DateTimeImmutable('2026-11-04 08:00:00', $paris),
        ));

        $loaded = $this->reload();

        self::assertSame('2026-11-04 07:00:00', $loaded->pendingExpiresAt()?->format('Y-m-d H:i:s'));
        self::assertSame('2026-11-02 10:00:00', $loaded->createdAt()->format('Y-m-d H:i:s'));
    }

    private function reload(): Appointment
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);
        $em->clear();

        $loaded = $this->repository->findById(AppointmentId::fromString(self::ID));
        self::assertNotNull($loaded);

        return $loaded;
    }

    private function request(): Appointment
    {
        return Appointment::request(
            id: AppointmentId::fromString(self::ID),
            clinicId: ClinicId::fromString(self::CLINIC),
            practitionerAssignee: new PractitionerAssignee(UserId::fromString(self::VET)),
            timeSlot: new TimeSlot(new \DateTimeImmutable('2026-11-04 09:00:00'), 20),
            reason: 'Vaccination',
            notes: 'Rappel annuel',
            createdAt: new \DateTimeImmutable('2026-11-02 10:00:00'),
            ownerId: self::OWNER,
            animalId: self::ANIMAL,
            bookingMotifId: self::MOTIF,
            source: AppointmentSource::PORTAL,
            pendingExpiresAt: new \DateTimeImmutable('2026-11-04 07:00:00'),
        );
    }
}
