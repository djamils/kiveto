<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Infrastructure\Adapter;

use App\Context\Animal\Application\Command\CreateAnimal\CreateAnimal;
use App\Context\Animal\Application\Query\FindRecentPetOfOwner\FindRecentPetOfOwner;
use App\Context\ClientPortal\Infrastructure\Adapter\Animal\CommandBusPetRegistration;
use App\Context\ClientPortal\Infrastructure\Adapter\Scheduling\CommandBusOnlineBooking;
use App\Context\Scheduling\Application\Command\BookOnlineAppointment\BookOnlineAppointment;
use App\Context\Scheduling\Application\Command\CancelOnlineAppointment\CancelOnlineAppointment;
use App\Context\Scheduling\Application\Command\RescheduleOnlineAppointment\RescheduleOnlineAppointment;
use App\Tests\Support\AnsweringQueryBus;
use App\Tests\Unit\Context\ClientPortal\Support\AnsweringCommandBus;
use PHPUnit\Framework\TestCase;

final class BookingCommandBusAdaptersTest extends TestCase
{
    public function testTheOnlineBookingCommandsOfScheduling(): void
    {
        $bus      = new AnsweringCommandBus(static fn (object $command): string => 'appointment-1');
        $booking  = new CommandBusOnlineBooking($bus);
        $startsAt = new \DateTimeImmutable('2026-11-05T10:00:00+01:00');

        self::assertSame('appointment-1', $booking->book('clinic-1', 'motif-1', 'vet-1', $startsAt, 'client-1', 'pet-1', 'pet-1'));
        self::assertSame('appointment-1', $booking->reschedule('clinic-1', 'appointment-1', 'vet-2', $startsAt));
        self::assertSame('appointment-1', $booking->cancel('clinic-1', 'appointment-1', 'other'));

        [$book, $move, $cancel] = $bus->commands;
        self::assertInstanceOf(BookOnlineAppointment::class, $book);
        self::assertSame(['clinic-1', 'motif-1', 'vet-1', $startsAt, 'client-1', 'pet-1', 'pet-1'], [$book->clinicId, $book->bookingMotifId, $book->practitionerUserId, $book->startsAt, $book->ownerId, $book->animalId, $book->createdPetId]);
        self::assertInstanceOf(RescheduleOnlineAppointment::class, $move);
        self::assertSame(['clinic-1', 'appointment-1', 'vet-2', $startsAt], [$move->clinicId, $move->appointmentId, $move->practitionerUserId, $move->startsAt]);
        self::assertInstanceOf(CancelOnlineAppointment::class, $cancel);
        self::assertSame(['clinic-1', 'appointment-1', 'other'], [$cancel->clinicId, $cancel->appointmentId, $cancel->cancellationReason]);
    }

    public function testANewPetIsCreatedWithTheDefaultsOfS4g(): void
    {
        $commands     = new AnsweringCommandBus(static fn (object $command): string => 'pet-9');
        $registration = new CommandBusPetRegistration($commands, new AnsweringQueryBus());

        self::assertSame('pet-9', $registration->register('clinic-1', 'client-1', 'Pixel', 'cat'));

        $create = $commands->commands[0];
        self::assertInstanceOf(CreateAnimal::class, $create);
        self::assertSame(
            ['clinic-1', 'Pixel', 'cat', 'unknown', 'unknown', false, 'none', 'alive', 'none', 'client-1', []],
            [$create->clinicId, $create->name, $create->species, $create->sex, $create->reproductiveStatus, $create->isMixedBreed, $create->registryType, $create->lifeStatus, $create->transferStatus, $create->primaryOwnerClientId, $create->secondaryOwnerClientIds],
        );
        self::assertSame(
            [null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null],
            [$create->breedName, $create->birthDate, $create->color, $create->photoUrl, $create->microchipNumber, $create->tattooNumber, $create->passportNumber, $create->registryNumber, $create->registryReference, $create->deceasedAt, $create->missingSince, $create->soldAt, $create->givenAt, $create->auxiliaryContactFirstName, $create->auxiliaryContactLastName, $create->auxiliaryContactPhoneNumber],
        );
    }

    public function testARecentPetOfTheOwnerIsFound(): void
    {
        $since   = new \DateTimeImmutable('2026-10-28 09:00:00');
        $queries = new AnsweringQueryBus([FindRecentPetOfOwner::class => 'pet-9']);

        self::assertSame('pet-9', new CommandBusPetRegistration(new AnsweringCommandBus(static fn (): null => null), $queries)->recentPetOf('clinic-1', 'pet-9', 'client-1', 'Pixel', 'cat', $since));
        self::assertNull(new CommandBusPetRegistration(new AnsweringCommandBus(static fn (): null => null), new AnsweringQueryBus())->recentPetOf('clinic-1', 'pet-9', 'client-1', 'Pixel', 'cat', $since));

        $query = $queries->asked[0];
        self::assertInstanceOf(FindRecentPetOfOwner::class, $query);
        self::assertSame(['clinic-1', 'pet-9', 'client-1', 'Pixel', 'cat', $since], [$query->clinicId, $query->animalId, $query->primaryOwnerClientId, $query->name, $query->species, $query->createdSince]);
    }
}
