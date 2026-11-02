<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Scheduling\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Scheduling\Domain\Appointment;
use App\Context\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\AppointmentSource;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Context\Scheduling\Domain\ValueObject\CancellationActor;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PractitionerAssignee;
use App\Context\Scheduling\Domain\ValueObject\TimeSlot;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Fixtures\Context\Scheduling\Factory\AppointmentEntityFactory;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

final class DoctrineAppointmentRepositoryTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC = '01960000-0000-7000-8000-0000000c8001';
    private const string OWNER  = '01960000-0000-7000-8000-0000000e8001';
    private const string PET    = '01960000-0000-7000-8000-0000000f8001';
    private const string MOTIF  = '01960000-0000-7000-8000-0000000b8001';
    private const string VET    = '01960000-0000-7000-8000-0000000d8001';

    private AppointmentRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $repo = self::getContainer()->get(AppointmentRepositoryInterface::class);
        \assert($repo instanceof AppointmentRepositoryInterface);
        $this->repository = $repo;
    }

    public function testFindByIdReturnsNullWhenAppointmentDoesNotExist(): void
    {
        $result = $this->repository->findById(
            AppointmentId::fromString('00000000-0000-0000-0000-000000000000'),
        );

        self::assertNull($result);
    }

    public function testSaveAndFindByIdRoundTrip(): void
    {
        $appointment = $this->makeAppointment();

        $this->repository->save($appointment);

        $loaded = $this->repository->findById($appointment->id());
        self::assertNotNull($loaded);
        self::assertTrue($loaded->id()->equals($appointment->id()));
        self::assertSame(AppointmentStatus::PLANNED, $loaded->status());
    }

    public function testFindByIdForUpdateReadsTheCommittedRowAgain(): void
    {
        // A row lock needs a transaction (the command bus opens one)
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        \assert($connection instanceof Connection);
        $connection->beginTransaction();

        self::assertNull($this->repository->findByIdForUpdate(AppointmentId::fromString('00000000-0000-0000-0000-000000000000')));

        $appointment = $this->makeAppointment();
        $this->repository->save($appointment);
        // Loaded once (kept by the entity manager), then changed behind its back
        $first = $this->repository->findById($appointment->id());
        self::assertNotNull($first);
        $connection->executeStatement("UPDATE scheduling__appointments SET status = 'CANCELLED' WHERE id = ?", [Uuid::fromString($appointment->id()->toString())->toBinary()]);

        $plain = $this->repository->findById($appointment->id());
        self::assertSame(AppointmentStatus::PLANNED, $plain?->status(), 'the plain read keeps what it loaded');
        self::assertSame(AppointmentStatus::CANCELLED, $this->repository->findByIdForUpdate($appointment->id())?->status());

        $connection->rollBack();
    }

    public function testExpiredPendingRequestsAreListedByExpiry(): void
    {
        $now = new \DateTimeImmutable('2026-10-15 09:00', new \DateTimeZone('UTC'));

        $expired = $this->pending('01960000-0000-7000-8000-0000000a7001', '2026-10-15 08:42');
        $atNow   = $this->pending('01960000-0000-7000-8000-0000000a7002', '2026-10-15 09:00');
        $later   = $this->pending('01960000-0000-7000-8000-0000000a7003', '2026-10-15 09:01');
        $handled = $this->pending('01960000-0000-7000-8000-0000000a7004', '2026-10-14 08:00');
        $handled->confirm();
        foreach ([$atNow, $expired, $later, $handled] as $appointment) {
            $this->repository->save($appointment);
        }

        self::assertSame(['01960000-0000-7000-8000-0000000a7001', '01960000-0000-7000-8000-0000000a7002'], $this->repository->expiredPendingIds($now));
    }

    public function testSaveUpdatesExistingAppointment(): void
    {
        $appointment = $this->makeAppointment();
        $this->repository->save($appointment);

        $appointment->cancel(CancellationActor::CLINIC);
        $this->repository->save($appointment);

        $loaded = $this->repository->findById($appointment->id());
        self::assertNotNull($loaded);
        self::assertSame(AppointmentStatus::CANCELLED, $loaded->status());
    }

    public function testSaveUpdatesMotifAndNoteOfExistingAppointment(): void
    {
        $appointment = $this->makeAppointment();
        $this->repository->save($appointment);

        $appointment->changeDetails('Vaccin', 'Rappel annuel');
        $this->repository->save($appointment);

        $loaded = $this->repository->findById($appointment->id());
        self::assertNotNull($loaded);
        self::assertSame('Vaccin', $loaded->reason());
        self::assertSame('Rappel annuel', $loaded->notes());
    }

    public function testHydratedEntityExposesUpdatedAt(): void
    {
        // Cover AppointmentEntity::getUpdatedAt — the only entity getter that wasn't
        // exercised by any other path because the domain Appointment doesn't expose it.
        $id        = '02345678-9abc-def0-1234-56789abcdef0';
        $updatedAt = new \DateTimeImmutable('2026-04-10 09:00:00');

        AppointmentEntityFactory::createOne([
            'id'        => Uuid::fromString($id),
            'updatedAt' => $updatedAt,
        ]);

        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        \assert($em instanceof \Doctrine\ORM\EntityManagerInterface);
        $entity = $em->find(
            \App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Entity\AppointmentEntity::class,
            Uuid::fromString($id),
        );
        self::assertNotNull($entity);
        self::assertSame(
            $updatedAt->format('Y-m-d H:i:s'),
            $entity->getUpdatedAt()->format('Y-m-d H:i:s'),
        );
    }

    public function testFindByIdRehydratesFromExistingFoundryEntity(): void
    {
        $id       = '01234567-89ab-cdef-0123-456789abcdef';
        $clinicId = '12345678-9abc-def0-1234-56789abcdef0';

        AppointmentEntityFactory::new()
            ->withId($id)
            ->withClinicId($clinicId)
            ->withPractitionerUserId('44444444-4444-4444-4444-444444444444')
            ->startingAt(new \DateTimeImmutable('2026-04-10 09:00:00'), 30)
            ->withStatus(AppointmentStatus::PLANNED)
            ->create()
        ;

        $loaded = $this->repository->findById(AppointmentId::fromString($id));

        self::assertNotNull($loaded);
        self::assertSame($id, $loaded->id()->toString());
        self::assertSame($clinicId, $loaded->clinicId()->toString());
        self::assertNull($loaded->linkedAdmissionId());
    }

    public function testLinkedAdmissionIdRoundTrip(): void
    {
        $admissionId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $appointment = Appointment::schedule(
            id: AppointmentId::fromString('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee'),
            clinicId: ClinicId::fromString('bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb'),
            practitionerAssignee: new PractitionerAssignee(
                UserId::fromString('eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee'),
            ),
            timeSlot: new TimeSlot(new \DateTimeImmutable('2026-04-10 09:00:00'), 30),
            reason: 'Linked to admission',
            notes: null,
            createdAt: new \DateTimeImmutable('2026-04-09 12:00:00'),
            linkedAdmissionId: $admissionId,
        );

        $this->repository->save($appointment);

        $loaded = $this->repository->findById($appointment->id());
        self::assertNotNull($loaded);
        self::assertSame($admissionId, $loaded->linkedAdmissionId());
    }

    public function testAPortalBookingKeepsItsMotifAndTheAnimalCreatedForIt(): void
    {
        $appointment = $this->portalBooking('01960000-0000-7000-8000-0000000a8001', self::utc('2026-04-13 05:00'));
        $this->repository->save($appointment);

        $loaded = $this->repository->findById($appointment->id());
        self::assertSame(AppointmentSource::PORTAL, $loaded?->source());
        self::assertSame(self::MOTIF, $loaded->bookingMotifId());
        self::assertSame(self::PET, $loaded->createdPetId());
    }

    public function testTheRecentOnlineBookingOfTheSameRequestIsFound(): void
    {
        // A row lock needs a transaction (the command bus opens one)
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        \assert($connection instanceof Connection);
        $connection->beginTransaction();

        $this->repository->save($this->portalBooking('01960000-0000-7000-8000-0000000a8001', self::utc('2026-04-13 05:00')));
        $cancelled = $this->portalBooking('01960000-0000-7000-8000-0000000a8002', self::utc('2026-04-13 05:30'));
        $cancelled->cancel(CancellationActor::CLIENT);
        $this->repository->save($cancelled);

        $clinic = ClinicId::fromString(self::CLINIC);
        $start  = new \DateTimeImmutable('2026-04-14T10:00:00+02:00');

        self::assertSame('01960000-0000-7000-8000-0000000a8001', $this->repository->findRecentOnlineBookingId($clinic, self::OWNER, self::PET, self::MOTIF, self::VET, $start, self::utc('2026-04-12 06:00'))?->toString());
        self::assertNull($this->repository->findRecentOnlineBookingId($clinic, self::OWNER, self::PET, self::MOTIF, self::VET, $start, self::utc('2026-04-13 05:00:01')), 'too old');
        self::assertNull($this->repository->findRecentOnlineBookingId($clinic, self::OWNER, self::PET, self::MOTIF, self::VET, $start->modify('+15 minutes'), self::utc('2026-04-12 06:00')), 'another start');
        self::assertNull($this->repository->findRecentOnlineBookingId($clinic, self::PET, self::PET, self::MOTIF, self::VET, $start, self::utc('2026-04-12 06:00')), 'another owner');
        self::assertNull($this->repository->findRecentOnlineBookingId($clinic, self::OWNER, self::PET, self::MOTIF, self::OWNER, $start, self::utc('2026-04-12 06:00')), 'another practitioner');

        $connection->rollBack();
    }

    public function testAnotherAppointmentOfTheAnimalIsOneNotWithdrawn(): void
    {
        $booking = $this->portalBooking('01960000-0000-7000-8000-0000000a8001', self::utc('2026-04-13 05:00'));
        $this->repository->save($booking);
        $clinic = ClinicId::fromString(self::CLINIC);

        self::assertFalse($this->repository->hasOtherAppointmentForAnimal($clinic, self::PET, $booking->id()));

        $cancelled = $this->portalBooking('01960000-0000-7000-8000-0000000a8002', self::utc('2026-04-13 05:30'));
        $cancelled->cancel(CancellationActor::CLIENT);
        $this->repository->save($cancelled);

        self::assertFalse($this->repository->hasOtherAppointmentForAnimal($clinic, self::PET, $booking->id()));

        $this->repository->save($this->portalBooking('01960000-0000-7000-8000-0000000a8003', self::utc('2026-04-13 05:40')));

        self::assertTrue($this->repository->hasOtherAppointmentForAnimal($clinic, self::PET, $booking->id()));
        self::assertFalse($this->repository->hasOtherAppointmentForAnimal(ClinicId::fromString('01960000-0000-7000-8000-0000000c8fff'), self::PET, $booking->id()));
    }

    private function portalBooking(string $id, \DateTimeImmutable $createdAt): Appointment
    {
        return Appointment::schedule(
            id: AppointmentId::fromString($id),
            clinicId: ClinicId::fromString(self::CLINIC),
            practitionerAssignee: new PractitionerAssignee(UserId::fromString(self::VET)),
            timeSlot: new TimeSlot(self::utc('2026-04-14 08:00'), 30),
            reason: 'Consultation',
            notes: null,
            createdAt: $createdAt,
            ownerId: self::OWNER,
            animalId: self::PET,
            source: AppointmentSource::PORTAL,
            bookingMotifId: self::MOTIF,
            createdPetId: self::PET,
        );
    }

    private static function utc(string $at): \DateTimeImmutable
    {
        return new \DateTimeImmutable($at, new \DateTimeZone('UTC'));
    }

    private function pending(string $id, string $expiresAtUtc): Appointment
    {
        return Appointment::request(
            id: AppointmentId::fromString($id),
            clinicId: ClinicId::fromString('01960000-0000-7000-8000-0000000c7001'),
            practitionerAssignee: new PractitionerAssignee(UserId::fromString('01960000-0000-7000-8000-0000000d7001')),
            timeSlot: new TimeSlot(new \DateTimeImmutable('2026-10-20 08:00', new \DateTimeZone('UTC')), 30),
            reason: 'Vaccination',
            notes: null,
            createdAt: new \DateTimeImmutable('2026-10-13 08:00', new \DateTimeZone('UTC')),
            ownerId: null,
            animalId: null,
            bookingMotifId: null,
            source: AppointmentSource::PORTAL,
            pendingExpiresAt: new \DateTimeImmutable($expiresAtUtc, new \DateTimeZone('UTC')),
        );
    }

    private function makeAppointment(): Appointment
    {
        return Appointment::schedule(
            id: AppointmentId::fromString('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa'),
            clinicId: ClinicId::fromString('bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb'),
            practitionerAssignee: new PractitionerAssignee(
                UserId::fromString('eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee'),
            ),
            timeSlot: new TimeSlot(new \DateTimeImmutable('2026-04-10 09:00:00'), 30),
            reason: 'Vaccination',
            notes: 'Anxious patient',
            createdAt: new \DateTimeImmutable('2026-04-09 12:00:00'),
        );
    }
}
