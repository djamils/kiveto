<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Command\Booking;

use App\Context\ClientPortal\Application\Command\BookPortalAppointment\BookPortalAppointment;
use App\Context\ClientPortal\Application\Command\BookPortalAppointment\BookPortalAppointmentHandler;
use App\Context\ClientPortal\Application\Command\BookPortalAppointmentWithNewPet\BookPortalAppointmentWithNewPet;
use App\Context\ClientPortal\Application\Command\BookPortalAppointmentWithNewPet\BookPortalAppointmentWithNewPetHandler;
use App\Context\ClientPortal\Application\Command\CancelPortalAppointment\CancelPortalAppointment;
use App\Context\ClientPortal\Application\Command\CancelPortalAppointment\CancelPortalAppointmentHandler;
use App\Context\ClientPortal\Application\Command\ReschedulePortalAppointment\ReschedulePortalAppointment;
use App\Context\ClientPortal\Application\Command\ReschedulePortalAppointment\ReschedulePortalAppointmentHandler;
use App\Context\ClientPortal\Application\Port\OnlineBookingInterface;
use App\Context\ClientPortal\Application\Port\OwnerAppointment;
use App\Context\ClientPortal\Application\Port\PetRegistrationInterface;
use App\Context\ClientPortal\Application\Service\PortalBookingGuard;
use App\Context\ClientPortal\Domain\Exception\AppointmentNotFoundException;
use App\Context\ClientPortal\Domain\Exception\ClinicNotLinkedException;
use App\Context\ClientPortal\Domain\Exception\InvalidNewPetException;
use App\Context\ClientPortal\Domain\Exception\PetNotBookableException;
use App\Context\ClientPortal\Domain\Exception\PetNotFoundException;
use App\Context\ClientPortal\Domain\Link\ClientLinkStatus;
use App\Tests\Unit\Context\ClientPortal\Support\InMemoryBookedPetMarkers;
use App\Tests\Unit\Context\ClientPortal\Support\PetWorld as W;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Task 26, S4b-F4: an owner books only an alive pet it sees, for the pet's
 * primary owner; changes only its own appointments; a new pet is
 * registered for the client of its oldest confirmed link.
 */
final class PortalBookingCommandsTest extends TestCase
{
    private const string MOTIF       = '01960000-0000-7000-8000-00000000b001';
    private const string VET         = '01960000-0000-7000-8000-00000000d001';
    private const string APPOINTMENT = '01960000-0000-7000-8000-00000000e001';

    private W $world;

    /** @var \ArrayObject<int, list<mixed>> */
    private \ArrayObject $calls;

    private ?string $recentPet = null;

    private InMemoryBookedPetMarkers $markers;

    protected function setUp(): void
    {
        $this->markers = new InMemoryBookedPetMarkers();
        $this->world   = new W();
        $this->world->now('2026-10-29 09:00');
        $this->calls = new \ArrayObject();
    }

    public function testBooksAVisiblePetForItsPrimaryOwner(): void
    {
        $this->world->link(W::CLIENT2);
        $this->world->pet(W::PET2, 'Rio', clientId: W::CLIENT2);
        $startsAt = new \DateTimeImmutable('2026-11-05T10:00:00+01:00');

        $id = $this->bookHandler()(new BookPortalAppointment(W::USER, W::CLINIC, W::PET2, self::MOTIF, self::VET, $startsAt));

        self::assertSame(self::APPOINTMENT, $id);
        self::assertSame([['book', W::CLINIC, self::MOTIF, self::VET, $startsAt, W::CLIENT2, W::PET2, null]], $this->calls->getArrayCopy());
    }

    /**
     * @param class-string<\Throwable> $expected
     */
    #[DataProvider('provideAPetThatCannotBeBookedCases')]
    public function testAPetThatCannotBeBooked(string $petId, string $lifeStatus, string $owner, string $expected): void
    {
        $this->world->pet(W::PET, 'Moka', $lifeStatus, $owner);

        try {
            $this->bookHandler()(new BookPortalAppointment(W::USER, W::CLINIC, $petId, self::MOTIF, self::VET, new \DateTimeImmutable()));
            self::fail('The booking should be refused.');
        } catch (\Throwable $e) {
            self::assertInstanceOf($expected, $e);
        }

        self::assertSame([], $this->calls->getArrayCopy());
    }

    /**
     * @return iterable<string, array{string, string, string, class-string<\Throwable>}>
     */
    public static function provideAPetThatCannotBeBookedCases(): iterable
    {
        yield 'deceased (AC 47)' => [W::PET, 'deceased', W::CLIENT, PetNotBookableException::class];
        yield 'missing' => [W::PET, 'missing', W::CLIENT, PetNotBookableException::class];
        yield 'another client\'s pet' => [W::PET, 'alive', W::CLIENT2, PetNotFoundException::class];
        yield 'unknown pet' => [W::PET3, 'alive', W::CLIENT, PetNotFoundException::class];
    }

    public function testANewPetIsRegisteredForTheOldestLinkThenBooked(): void
    {
        $this->world->link(W::CLIENT2);
        $startsAt = new \DateTimeImmutable('2026-11-05T10:00:00+01:00');

        $id = $this->newPetHandler()(new BookPortalAppointmentWithNewPet(W::USER, W::CLINIC, '  Pixel ', 'cat', self::MOTIF, self::VET, $startsAt, 'key-1'));

        self::assertSame(self::APPOINTMENT, $id);
        self::assertSame([
            ['register', W::CLINIC, W::CLIENT, 'Pixel', 'cat'],
            ['book', W::CLINIC, self::MOTIF, self::VET, $startsAt, W::CLIENT, W::PET3, W::PET3],
        ], $this->calls->getArrayCopy());
        // The request's marker remembers the animal it registered
        self::assertSame([self::marker('key-1')], array_keys($this->markers->rows));
        self::assertSame([W::PET3, '2026-10-29T09:00:00+00:00'], [$this->markers->rows[self::marker('key-1')][0], $this->markers->rows[self::marker('key-1')][1]->format('c')]);
    }

    public function testARetryOfTheSameRequestReusesTheAnimalItRegistered(): void
    {
        // S4B-01: same account, same Idempotency-Key
        $this->markers->rows[self::marker('key-1')] = [W::PET2, new \DateTimeImmutable('2026-10-29 08:00', new \DateTimeZone('UTC'))];
        $this->recentPet                            = W::PET2;

        $this->newPetHandler()(new BookPortalAppointmentWithNewPet(W::USER, W::CLINIC, 'pixel', 'cat', self::MOTIF, self::VET, new \DateTimeImmutable('2026-11-05T10:00:00+01:00'), 'key-1'));

        self::assertSame(['recentPetOf', 'book'], array_column($this->calls->getArrayCopy(), 0));
        self::assertSame([W::CLINIC, W::PET2, W::CLIENT, 'pixel', 'cat', '2026-10-28T09:00:00+00:00'], \array_slice($this->calls[0] ?? [], 1));
        self::assertSame([W::PET2, W::PET2], \array_slice($this->calls[1] ?? [], 6));
    }

    public function testAnAnimalOfTheClientNotRegisteredByThisRequestIsNeverReused(): void
    {
        // S4B-01: Rex, created by the clinic (or by another request), would match by name: it is not even looked up
        $this->markers->rows[self::marker('key-other')] = [W::PET2, new \DateTimeImmutable('2026-10-29 08:00', new \DateTimeZone('UTC'))];
        $this->recentPet                                = W::PET2;

        $this->newPetHandler()(new BookPortalAppointmentWithNewPet(W::USER, W::CLINIC, 'Rex', 'dog', self::MOTIF, self::VET, new \DateTimeImmutable('2026-11-05T10:00:00+01:00'), 'key-1'));
        $this->newPetHandler()(new BookPortalAppointmentWithNewPet(W::USER, W::CLINIC, 'Rex', 'dog', self::MOTIF, self::VET, new \DateTimeImmutable('2026-11-05T10:00:00+01:00')));

        self::assertSame(['register', 'book', 'register', 'book'], array_column($this->calls->getArrayCopy(), 0));
        self::assertSame([W::PET3, W::PET3], \array_slice($this->calls[1] ?? [], 6));
    }

    public function testTheAnimalOfAnEarlierAttemptNoLongerReusableIsReplaced(): void
    {
        // Archived, dead or transferred meanwhile: Animal finds nothing, a new animal is registered and remembered
        $this->markers->rows[self::marker('key-1')] = [W::PET2, new \DateTimeImmutable('2026-10-29 08:00', new \DateTimeZone('UTC'))];

        $this->newPetHandler()(new BookPortalAppointmentWithNewPet(W::USER, W::CLINIC, 'Pixel', 'cat', self::MOTIF, self::VET, new \DateTimeImmutable('2026-11-05T10:00:00+01:00'), 'key-1'));

        self::assertSame(['recentPetOf', 'register', 'book'], array_column($this->calls->getArrayCopy(), 0));
        self::assertSame(W::PET3, $this->markers->rows[self::marker('key-1')][0] ?? null);
    }

    /**
     * @param class-string<\Throwable> $expected
     */
    #[DataProvider('provideAnInvalidNewPetCases')]
    public function testAnInvalidNewPet(string $name, string $species, bool $linked, string $expected, ?string $field): void
    {
        // The account's only link is to another clinic, or revoked
        $clinicId = $linked ? W::CLINIC : '01960000-0000-7000-8000-0000000c0fff';
        $this->world->link(W::CLIENT2, ClientLinkStatus::REVOKED, $clinicId);

        try {
            $this->newPetHandler()(new BookPortalAppointmentWithNewPet(W::USER, $clinicId, $name, $species, self::MOTIF, self::VET, new \DateTimeImmutable()));
            self::fail('The booking should be refused.');
        } catch (\Throwable $e) {
            self::assertInstanceOf($expected, $e);

            if ($e instanceof InvalidNewPetException) {
                self::assertSame($field, $e->field);
            }
        }

        self::assertSame([], $this->calls->getArrayCopy());
    }

    /**
     * @return iterable<string, array{string, string, bool, class-string<\Throwable>, ?string}>
     */
    public static function provideAnInvalidNewPetCases(): iterable
    {
        yield 'blank name' => ['  ', 'cat', true, InvalidNewPetException::class, InvalidNewPetException::NAME];
        yield 'name too long' => [str_repeat('é', 81), 'cat', true, InvalidNewPetException::class, InvalidNewPetException::NAME];
        yield 'unknown species (AC 46)' => ['Pixel', 'horse', true, InvalidNewPetException::class, InvalidNewPetException::SPECIES];
        yield 'no confirmed link in the clinic' => ['Pixel', 'cat', false, ClinicNotLinkedException::class, null];
    }

    public function testTheOwnerMovesAndCancelsTheirAppointment(): void
    {
        $startsAt                           = new \DateTimeImmutable('2026-11-06T10:00:00+01:00');
        $this->world->fakes->appointments[] = self::appointment(W::CLIENT, null);

        self::assertSame(self::APPOINTMENT, $this->rescheduleHandler()(new ReschedulePortalAppointment(W::USER, W::CLINIC, self::APPOINTMENT, self::VET, $startsAt)));
        self::assertSame(self::APPOINTMENT, $this->cancelHandler()(new CancelPortalAppointment(W::USER, W::CLINIC, self::APPOINTMENT, 'unavailable')));

        self::assertSame([
            ['reschedule', W::CLINIC, self::APPOINTMENT, self::VET, $startsAt],
            ['cancel', W::CLINIC, self::APPOINTMENT, 'unavailable'],
        ], $this->calls->getArrayCopy());
    }

    public function testAnAppointmentOfAVisiblePetOfAnotherOwnerFileIsTheAccounts(): void
    {
        $this->world->pet();
        $this->world->fakes->appointments[] = self::appointment(W::CLIENT2, W::PET);

        $this->cancelHandler()(new CancelPortalAppointment(W::USER, W::CLINIC, self::APPOINTMENT));

        self::assertSame([['cancel', W::CLINIC, self::APPOINTMENT, null]], $this->calls->getArrayCopy());
    }

    public function testAnotherClientsAppointmentIsNotFound(): void
    {
        $this->world->fakes->appointments[] = self::appointment(W::CLIENT2, W::PET2);

        try {
            $this->rescheduleHandler()(new ReschedulePortalAppointment(W::USER, W::CLINIC, self::APPOINTMENT, self::VET, new \DateTimeImmutable()));
            self::fail('The appointment is not the account\'s.');
        } catch (AppointmentNotFoundException $e) {
            self::assertSame(self::APPOINTMENT, $e->appointmentId);
        }

        $this->expectException(AppointmentNotFoundException::class);

        $this->cancelHandler()(new CancelPortalAppointment(W::USER, W::CLINIC, '01960000-0000-7000-8000-00000000e0ff'));
    }

    public static function appointmentId(): string
    {
        return self::APPOINTMENT;
    }

    private function bookHandler(): BookPortalAppointmentHandler
    {
        return new BookPortalAppointmentHandler($this->guard(), $this->booking());
    }

    private function newPetHandler(): BookPortalAppointmentWithNewPetHandler
    {
        return new BookPortalAppointmentWithNewPetHandler($this->world->ownerPets(), $this->registration(), $this->booking(), $this->markers, $this->world->documents->clock);
    }

    private function rescheduleHandler(): ReschedulePortalAppointmentHandler
    {
        return new ReschedulePortalAppointmentHandler($this->guard(), $this->booking());
    }

    private function cancelHandler(): CancelPortalAppointmentHandler
    {
        return new CancelPortalAppointmentHandler($this->guard(), $this->booking());
    }

    private function guard(): PortalBookingGuard
    {
        return new PortalBookingGuard($this->world->ownerPets(), $this->world->fakes);
    }

    private function booking(): OnlineBookingInterface
    {
        return new class($this->calls) implements OnlineBookingInterface {
            /** @param \ArrayObject<int, list<mixed>> $calls */
            public function __construct(private readonly \ArrayObject $calls)
            {
            }

            public function book(string $clinicId, string $motifId, string $practitionerId, \DateTimeImmutable $startsAt, string $ownerId, string $animalId, ?string $createdPetId = null): string
            {
                $this->calls->append(['book', $clinicId, $motifId, $practitionerId, $startsAt, $ownerId, $animalId, $createdPetId]);

                return PortalBookingCommandsTest::appointmentId();
            }

            public function reschedule(string $clinicId, string $appointmentId, string $practitionerId, \DateTimeImmutable $startsAt): string
            {
                $this->calls->append(['reschedule', $clinicId, $appointmentId, $practitionerId, $startsAt]);

                return $appointmentId;
            }

            public function cancel(string $clinicId, string $appointmentId, ?string $reason): string
            {
                $this->calls->append(['cancel', $clinicId, $appointmentId, $reason]);

                return $appointmentId;
            }

            public function requestNewClientSlot(string $clinicId, string $motifId, string $practitionerId, \DateTimeImmutable $startsAt): string
            {
                throw new \LogicException('Not a new-client booking.');
            }

            public function confirmNewClient(string $clinicId, string $appointmentId, string $ownerId, string $animalId, bool $silent = false): void
            {
                throw new \LogicException('Not a new-client booking.');
            }

            public function decline(string $clinicId, string $appointmentId): void
            {
                throw new \LogicException('Not a new-client booking.');
            }

            public function withdrawPending(string $clinicId, string $appointmentId): void
            {
                throw new \LogicException('Not a new-client booking.');
            }
        };
    }

    private function registration(): PetRegistrationInterface
    {
        return new class($this->calls, $this->recentPet) implements PetRegistrationInterface {
            /** @param \ArrayObject<int, list<mixed>> $calls */
            public function __construct(private readonly \ArrayObject $calls, private readonly ?string $recentPet)
            {
            }

            public function recentPetOf(string $clinicId, string $animalId, string $ownerClientId, string $name, string $species, \DateTimeImmutable $since): ?string
            {
                $this->calls->append(['recentPetOf', $clinicId, $animalId, $ownerClientId, $name, $species, $since->format('c')]);

                return $this->recentPet;
            }

            public function register(string $clinicId, string $ownerClientId, string $name, string $species): string
            {
                $this->calls->append(['register', $clinicId, $ownerClientId, $name, $species]);

                return W::PET3;
            }
        };
    }

    private static function marker(string $key): string
    {
        return hash('sha256', W::USER . "\n" . $key);
    }

    private static function appointment(string $ownerId, ?string $animalId): OwnerAppointment
    {
        $utc = new \DateTimeZone('UTC');

        return new OwnerAppointment(
            self::APPOINTMENT,
            $ownerId,
            $animalId,
            self::VET,
            new \DateTimeImmutable('2026-11-05 09:00:00', $utc),
            30,
            'confirmed',
            true,
            false,
            'portal',
            self::MOTIF,
            'Consultation',
            null,
            new \DateTimeImmutable('2026-10-01 08:00:00', $utc),
            null,
        );
    }
}
