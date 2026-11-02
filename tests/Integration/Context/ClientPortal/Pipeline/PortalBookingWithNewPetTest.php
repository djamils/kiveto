<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\ClientPortal\Pipeline;

use App\Context\Animal\Application\Command\CreateAnimal\CreateAnimal;
use App\Context\ClientPortal\Application\Command\BookPortalAppointment\BookPortalAppointment;
use App\Context\ClientPortal\Application\Command\BookPortalAppointmentWithNewPet\BookPortalAppointmentWithNewPet;
use App\Context\ClientPortal\Domain\Link\ClientLink;
use App\Context\ClientPortal\Domain\Link\ClientLinkId;
use App\Context\ClientPortal\Domain\Link\ClientLinkRepositoryInterface;
use App\Context\Scheduling\Application\Command\DeclineAppointment\DeclineAppointment;
use App\Context\Scheduling\Application\Port\ClinicOpeningHours;
use App\Context\Scheduling\Application\Port\ClinicOpeningHoursInterface;
use App\Context\Scheduling\Application\Port\ClinicTimezoneResolverInterface;
use App\Context\Scheduling\Application\Port\MembershipEligibilityCheckerInterface;
use App\Context\Scheduling\Application\Port\PlanningBlockOccurrence;
use App\Context\Scheduling\Application\Port\PlanningBlockOccurrencesReaderInterface;
use App\Context\Scheduling\Domain\Appointment;
use App\Context\Scheduling\Domain\Booking\BookingMotif;
use App\Context\Scheduling\Domain\Booking\BookingMotifId;
use App\Context\Scheduling\Domain\Booking\BookingMotifRepositoryInterface;
use App\Context\Scheduling\Domain\Booking\ConfirmationMode;
use App\Context\Scheduling\Domain\Booking\OnlineBookingSettings;
use App\Context\Scheduling\Domain\Booking\OnlineBookingSettingsRepositoryInterface;
use App\Context\Scheduling\Domain\Exception\SlotTakenException;
use App\Context\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockType;
use App\Context\Scheduling\Domain\ValueObject\PractitionerAssignee;
use App\Context\Scheduling\Domain\ValueObject\TimeSlot;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\Context\Scheduling\Support\FakeAvailabilitySources;
use App\Tests\Unit\Context\Scheduling\Support\ParisTimezone;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * AC 46, AC 151 and AC 154 through the real command bus: the new pet is
 * created and booked in one transaction (a refused booking leaves no
 * animal), a retry of the same request reuses it (never an animal the
 * clinic created, S4B-01), and a declined request archives it unless it
 * has another appointment. The clinic's opening hours, the practitioner's
 * eligibility and blocks are doubles; everything else is real.
 */
final class PortalBookingWithNewPetTest extends KernelTestCase
{
    private const string CLINIC = '01960000-0000-7000-8000-0000000c6e01';
    private const string USER   = '01960000-0000-7000-8000-000000016e01';
    private const string CLIENT = '01960000-0000-7000-8000-0000000e6e01';
    private const string OTHER  = '01960000-0000-7000-8000-0000000e6e02';
    private const string VET    = '01960000-0000-7000-8000-0000000d6e01';
    private const string AUTO   = '01960000-0000-7000-8000-0000000b6e01';
    private const string MANUAL = '01960000-0000-7000-8000-0000000b6e02';

    private CommandBusInterface $bus;
    private Connection $connection;

    protected function setUp(): void
    {
        $container = self::getContainer();
        $sources   = new FakeAvailabilitySources();
        $weekday   = [['opensAt' => '08:30', 'closesAt' => '12:30']];

        $sources->clinic      = new ClinicOpeningHours(true, new \DateTimeZone('Europe/Paris'), [1 => $weekday, 2 => $weekday, 3 => $weekday], []);
        $sources->eligible    = [self::VET];
        $sources->occurrences = [
            new PlanningBlockOccurrence('2026-04-14', '09:00', '12:00', PlanningBlockType::CONSULTATION, 0),
            new PlanningBlockOccurrence('2026-04-15', '09:00', '12:00', PlanningBlockType::CONSULTATION, 0),
        ];

        $container->set(ClinicOpeningHoursInterface::class, $sources);
        $container->set(MembershipEligibilityCheckerInterface::class, $sources);
        $container->set(PlanningBlockOccurrencesReaderInterface::class, $sources);
        $container->set(ClinicTimezoneResolverInterface::class, new ParisTimezone());
        // Monday 13 April 2026, 08:00 in Paris
        $container->set(ClockInterface::class, new FrozenClock(new \DateTimeImmutable('2026-04-13 06:00', new \DateTimeZone('UTC'))));

        $bus = $container->get(CommandBusInterface::class);
        \assert($bus instanceof CommandBusInterface);
        $this->bus = $bus;

        $connection = $container->get('doctrine.dbal.default_connection');
        \assert($connection instanceof Connection);
        $this->connection = $connection;

        $settings = $container->get(OnlineBookingSettingsRepositoryInterface::class);
        \assert($settings instanceof OnlineBookingSettingsRepositoryInterface);
        $settings->save(new OnlineBookingSettings(ClinicId::fromString(self::CLINIC), true, 15, 120, 24, 60, 24, false));

        $motifs = $container->get(BookingMotifRepositoryInterface::class);
        \assert($motifs instanceof BookingMotifRepositoryInterface);
        foreach ([self::AUTO => ConfirmationMode::AUTO, self::MANUAL => ConfirmationMode::MANUAL] as $id => $mode) {
            $motifs->save(BookingMotif::create(
                BookingMotifId::fromString($id),
                ClinicId::fromString(self::CLINIC),
                ConfirmationMode::AUTO === $mode ? 'Consultation' : 'Vaccination',
                null,
                30,
                true,
                $mode,
                [PlanningBlockType::CONSULTATION],
                [UserId::fromString(self::VET)],
                null,
                true,
                0,
            ));
        }

        $links = $container->get(ClientLinkRepositoryInterface::class);
        \assert($links instanceof ClientLinkRepositoryInterface);
        $links->save(ClientLink::confirm(ClientLinkId::fromString(Uuid::v7()->toRfc4122()), self::USER, self::CLINIC, self::CLIENT, new \DateTimeImmutable('2026-01-10 10:00')));
    }

    public function testANewPetIsRegisteredAndBookedThenReusedByARetryOfTheSameRequest(): void
    {
        $id = $this->withNewPet(self::AUTO, '2026-04-14T10:00:00+02:00', 'Pixel', 'key-1');

        $appointment = $this->appointment($id);
        self::assertSame(AppointmentStatus::PLANNED, $appointment->status());
        self::assertSame(self::CLIENT, $appointment->ownerId());
        self::assertSame($appointment->animalId(), $appointment->createdPetId());
        self::assertSame([['Pixel', 'cat', 'unknown', 'unknown', 'none', 'alive', 'none', 'active']], $this->animalsOfTheClient());

        // The same request again (its response lost, same Idempotency-Key): the same appointment, the same animal
        self::assertSame($id, $this->withNewPet(self::AUTO, '2026-04-14T10:00:00+02:00', 'Pixel', 'key-1'));
        self::assertCount(1, $this->animalsOfTheClient());

        // S4B-01: another request (another key) registers its own animal, never reusing one by its name
        $other = $this->withNewPet(self::AUTO, '2026-04-14T11:00:00+02:00', 'PIXEL', 'key-2');

        self::assertNotSame($id, $other);
        self::assertNotSame($appointment->animalId(), $this->appointment($other)->animalId());
        self::assertCount(2, $this->animalsOfTheClient());
    }

    public function testAnAnimalTheClinicCreatedIsNeverReusedNorArchived(): void
    {
        // S4B-01: Rex, registered by the clinic this morning for the same client
        $rex = $this->bus->dispatch(new CreateAnimal(
            clinicId: self::CLINIC,
            name: 'Rex',
            species: 'cat',
            sex: 'male',
            reproductiveStatus: 'intact',
            isMixedBreed: false,
            breedName: null,
            birthDate: null,
            color: null,
            photoUrl: null,
            microchipNumber: null,
            tattooNumber: null,
            passportNumber: null,
            registryType: 'none',
            registryNumber: null,
            registryReference: null,
            lifeStatus: 'alive',
            deceasedAt: null,
            missingSince: null,
            transferStatus: 'none',
            soldAt: null,
            givenAt: null,
            auxiliaryContactFirstName: null,
            auxiliaryContactLastName: null,
            auxiliaryContactPhoneNumber: null,
            primaryOwnerClientId: self::CLIENT,
            secondaryOwnerClientIds: [],
        ));
        \assert(\is_string($rex));

        $id      = $this->withNewPet(self::MANUAL, '2026-04-15T10:00:00+02:00', 'rex', 'key-1');
        $created = $this->appointment($id)->createdPetId();
        self::assertNotSame($rex, $created);
        self::assertSame($created, $this->appointment($id)->animalId());

        $this->bus->dispatch(new DeclineAppointment($id, self::CLINIC));

        self::assertSame([[$rex, 'active'], [$created, 'archived']], $this->statusesOf([$rex, (string) $created]));
    }

    public function testATakenSlotLeavesNoAnimal(): void
    {
        $appointments = self::getContainer()->get(AppointmentRepositoryInterface::class);
        \assert($appointments instanceof AppointmentRepositoryInterface);
        $appointments->save(Appointment::schedule(
            id: AppointmentId::fromString(Uuid::v7()->toRfc4122()),
            clinicId: ClinicId::fromString(self::CLINIC),
            practitionerAssignee: new PractitionerAssignee(UserId::fromString(self::VET)),
            timeSlot: new TimeSlot(new \DateTimeImmutable('2026-04-14 08:00', new \DateTimeZone('UTC')), 30),
            reason: 'Consultation',
            notes: null,
            createdAt: new \DateTimeImmutable('2026-04-10 08:00', new \DateTimeZone('UTC')),
            ownerId: self::OTHER,
        ));

        try {
            $this->withNewPet(self::AUTO, '2026-04-14T10:00:00+02:00', 'Pixel');
            self::fail('The slot is taken.');
        } catch (SlotTakenException) {
            // 409 `slot_taken`
        }

        self::assertSame([], $this->animalsOfTheClient());
    }

    public function testADeclinedRequestArchivesTheAnimalRegisteredForIt(): void
    {
        $id = $this->withNewPet(self::MANUAL, '2026-04-15T10:00:00+02:00', 'Pixel');
        self::assertSame(AppointmentStatus::PENDING_CLINIC_CONFIRMATION, $this->appointment($id)->status());

        $this->bus->dispatch(new DeclineAppointment($id, self::CLINIC));

        self::assertSame(AppointmentStatus::DECLINED, $this->appointment($id)->status());
        self::assertSame('archived', $this->animalsOfTheClient()[0][7]);
    }

    public function testAnAnimalBookedAgainIsKeptWhenTheFirstRequestIsDeclined(): void
    {
        $id    = $this->withNewPet(self::MANUAL, '2026-04-15T10:00:00+02:00', 'Pixel');
        $petId = $this->appointment($id)->animalId();
        \assert(null !== $petId);
        $this->bus->dispatch(new BookPortalAppointment(self::USER, self::CLINIC, $petId, self::AUTO, self::VET, new \DateTimeImmutable('2026-04-14T10:00:00+02:00')));

        $this->bus->dispatch(new DeclineAppointment($id, self::CLINIC));

        self::assertSame('active', $this->animalsOfTheClient()[0][7]);
    }

    private function withNewPet(string $motifId, string $startsAt, string $name, ?string $key = null): string
    {
        $id = $this->bus->dispatch(new BookPortalAppointmentWithNewPet(self::USER, self::CLINIC, $name, 'cat', $motifId, self::VET, new \DateTimeImmutable($startsAt), $key));
        \assert(\is_string($id));

        return $id;
    }

    private function appointment(string $id): Appointment
    {
        $appointments = self::getContainer()->get(AppointmentRepositoryInterface::class);
        \assert($appointments instanceof AppointmentRepositoryInterface);

        return $appointments->findById(AppointmentId::fromString($id)) ?? throw new \LogicException('Unknown appointment.');
    }

    /**
     * @param list<string> $ids
     *
     * @return list<list<string>> id, status
     */
    private function statusesOf(array $ids): array
    {
        return array_map(
            fn (string $id): array => [$id, \is_string($status = $this->connection->fetchOne('SELECT status FROM animal__animals WHERE id = ?', [Uuid::fromString($id)->toBinary()])) ? $status : ''],
            $ids,
        );
    }

    /**
     * @return list<list<mixed>> name, species, sex, reproductive status, registry, life, transfer, status
     */
    private function animalsOfTheClient(): array
    {
        return $this->connection->fetchAllNumeric(
            'SELECT a.name, a.species, a.sex, a.reproductive_status, a.registry_type, a.life_status, a.transfer_status, a.status
             FROM animal__animals a JOIN animal__ownerships o ON o.animal_id = a.id
             WHERE a.clinic_id = ? AND o.client_id = ? AND o.role = ?',
            [Uuid::fromString(self::CLINIC)->toBinary(), Uuid::fromString(self::CLIENT)->toBinary(), 'primary'],
        );
    }
}
