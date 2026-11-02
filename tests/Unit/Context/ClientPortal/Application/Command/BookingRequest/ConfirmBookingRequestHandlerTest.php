<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Command\BookingRequest;

use App\Context\ClientPortal\Application\Command\ConfirmBookingRequest\ConfirmBookingRequest;
use App\Context\ClientPortal\Application\Port\MobileFileMatch;
use App\Context\ClientPortal\Domain\Booking\BookingRequestStatus;
use App\Context\ClientPortal\Domain\Event\ClientLinkConfirmed;
use App\Context\ClientPortal\Domain\Exception\BookingRequestAlreadyResolvedException;
use App\Context\ClientPortal\Domain\Exception\BookingRequestNotFoundException;
use App\Context\ClientPortal\Domain\Exception\InvalidBookingChoiceException;
use App\Context\ClientPortal\Domain\Link\ClientLink;
use App\Context\ClientPortal\Domain\Link\ClientLinkId;
use App\Context\ClientPortal\Domain\Link\ClientLinkStatus;
use App\Context\ClientPortal\Domain\Profile\OwnerProfile;
use App\Tests\Unit\Context\ClientPortal\Support\BookingRequestWorld as W;
use App\Tests\Unit\Context\ClientPortal\Support\InMemoryOwnerProfiles;
use PHPUnit\Framework\TestCase;

/** S7d, AC 80, AC 268: the file, the animal, the link and the appointment in one go. */
final class ConfirmBookingRequestHandlerTest extends TestCase
{
    private const string REQUEST   = '01960000-0000-7000-8000-0000000d0001';
    private const string SIBLING   = '01960000-0000-7000-8000-0000000d0002';
    private const string ELSEWHERE = '01960000-0000-7000-8000-0000000d0003';

    private W $world;

    protected function setUp(): void
    {
        $this->world = new W();
        $this->world->pending(self::REQUEST);
        $this->world->fakes->mobileMatches[W::PHONE] = [
            new MobileFileMatch(W::CLINIC, W::CLIENT, 'Sophie', 'Martin', ['Oslo']),
            new MobileFileMatch(W::CLINIC_2, '01960000-0000-7000-8000-0000000c5002', 'Sophie', 'Martin', []),
        ];
        $this->world->pets[] = W::pet(W::PET, W::CLIENT, 'Oslo', 'dog');
    }

    public function testANewFileAndTheNewPetAreCreatedAndTheSiblingRequestFollows(): void
    {
        $this->world->pending(self::SIBLING, createdAt: '2026-11-01 10:00:00');
        $this->world->pending(self::ELSEWHERE, W::CLINIC_2, '2026-11-01 11:00:00');

        $this->confirm(ConfirmBookingRequest::NEW, null, ConfirmBookingRequest::NEW, null);

        self::assertSame([[W::CLINIC, 'Sophie', 'Martin', W::PHONE, W::VET]], $this->world->registeredClients);
        self::assertSame([[W::CLINIC, W::NEW_FILE, 'Pixel', 'cat']], $this->world->registeredPets);
        self::assertSame([['confirm', W::CLINIC, self::REQUEST, W::NEW_FILE, W::NEW_PET]], $this->world->booking->calls);

        [$link] = array_values($this->world->links->links);
        self::assertSame([W::USER, W::CLINIC, W::NEW_FILE, ClientLinkStatus::CONFIRMED], [$link->userId(), $link->clinicId(), $link->clientId(), $link->status()]);
        self::assertInstanceOf(ClientLinkConfirmed::class, $this->world->fakes->events[0] ?? null);

        $request = $this->world->requests->requests[self::REQUEST];
        self::assertSame([BookingRequestStatus::CONFIRMED, W::NEW_FILE, '2026-11-02T10:00:00+00:00'], [$request->status(), $request->clientId(), $request->resolvedAt()?->format('c')]);
        // S6-F3: who answered
        self::assertSame([W::VET, 'Dr Vet'], [$request->resolvedByUserId(), $request->resolvedByName()]);
        // AC 268: the other pending request at the clinic carries the same file; another clinic's does not
        self::assertSame([W::NEW_FILE, null], [$this->world->requests->requests[self::SIBLING]->clientId(), $this->world->requests->requests[self::ELSEWHERE]->clientId()]);
        self::assertSame([self::REQUEST, 'user:' . W::USER], $this->world->requests->lockedForUpdate);
    }

    public function testASiblingRequestConfirmedSecondCreatesNoSecondFile(): void
    {
        $this->world->requests->requests[self::REQUEST]->attachClient(W::CLIENT);

        $this->confirm(ConfirmBookingRequest::NEW, null, ConfirmBookingRequest::EXISTING, W::PET);

        self::assertSame([], $this->world->registeredClients);
        self::assertSame([['confirm', W::CLINIC, self::REQUEST, W::CLIENT, W::PET]], $this->world->booking->calls);
    }

    public function testAnExistingFileAndAnimalReactivateTheLink(): void
    {
        $this->world->links->save(ClientLink::reconstitute(ClientLinkId::fromString('01960000-0000-7000-8000-0000000c7001'), W::USER, W::CLINIC, W::CLIENT, ClientLinkStatus::REVOKED, W::utc('2026-01-01 08:00:00'), W::utc('2026-02-01 08:00:00')));

        $this->confirm(ConfirmBookingRequest::EXISTING, W::CLIENT, ConfirmBookingRequest::EXISTING, W::PET);

        self::assertSame([[], []], [$this->world->registeredClients, $this->world->registeredPets]);
        self::assertSame(ClientLinkStatus::CONFIRMED, $this->world->links->links['01960000-0000-7000-8000-0000000c7001']->status());
        self::assertSame(W::CLIENT, $this->world->requests->requests[self::REQUEST]->clientId());
    }

    public function testWithoutProfileTheNewFileIsNamedAfterTheRequest(): void
    {
        $this->world->profiles = new InMemoryOwnerProfiles();
        $this->world->profiles->save(OwnerProfile::register('01960000-0000-7000-8000-0000000c0fff', 'X', 'Y', 'v1', W::utc(W::NOW)));

        $this->confirm(ConfirmBookingRequest::NEW, null, ConfirmBookingRequest::NEW, null);

        self::assertSame([[W::CLINIC, 'Sophie', 'Martin', W::PHONE, W::VET]], $this->world->registeredClients);
    }

    /**
     * @return iterable<string, array{string, ?string, string, ?string, string}>
     */
    public static function provideAChoiceStaffWereNotOfferedIsRefusedCases(): iterable
    {
        yield 'no file' => [ConfirmBookingRequest::EXISTING, null, ConfirmBookingRequest::NEW, null, InvalidBookingChoiceException::CLIENT];
        yield 'another clinic\'s file' => [ConfirmBookingRequest::EXISTING, '01960000-0000-7000-8000-0000000c5002', ConfirmBookingRequest::NEW, null, InvalidBookingChoiceException::CLIENT];
        yield 'no animal' => [ConfirmBookingRequest::EXISTING, W::CLIENT, ConfirmBookingRequest::EXISTING, null, InvalidBookingChoiceException::ANIMAL];
        yield 'another client\'s animal' => [ConfirmBookingRequest::EXISTING, W::CLIENT, ConfirmBookingRequest::EXISTING, '01960000-0000-7000-8000-0000000c6fff', InvalidBookingChoiceException::ANIMAL];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('provideAChoiceStaffWereNotOfferedIsRefusedCases')]
    public function testAChoiceStaffWereNotOfferedIsRefused(string $client, ?string $clientId, string $animal, ?string $animalId, string $field): void
    {
        try {
            $this->confirm($client, $clientId, $animal, $animalId);
            self::fail('The choice should be refused.');
        } catch (InvalidBookingChoiceException $e) {
            self::assertSame($field, $e->field);
        }

        self::assertSame([], $this->world->booking->calls);
        self::assertTrue($this->world->requests->requests[self::REQUEST]->isPending());
    }

    public function testAnUnknownChoiceIsAProgrammingError(): void
    {
        foreach ([['maybe', ConfirmBookingRequest::NEW], [ConfirmBookingRequest::NEW, 'maybe']] as [$client, $animal]) {
            try {
                $this->confirm($client, null, $animal, null);
                self::fail('Unknown choice.');
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString('"maybe"', $e->getMessage());
            }
        }
    }

    public function testAnUnknownOrResolvedRequestIsRefused(): void
    {
        try {
            $this->world->confirmHandler()(new ConfirmBookingRequest(W::CLINIC_2, self::REQUEST, ConfirmBookingRequest::NEW, null, ConfirmBookingRequest::NEW, null, W::VET, 'Dr Vet'));
            self::fail('Another clinic\'s request.');
        } catch (BookingRequestNotFoundException $e) {
            self::assertSame(self::REQUEST, $e->requestId);
        }

        $this->world->requests->requests[self::REQUEST]->confirm(W::CLIENT, W::utc(W::NOW));

        $this->expectException(BookingRequestAlreadyResolvedException::class);
        $this->expectExceptionMessage('Demande déjà traitée.');

        $this->confirm(ConfirmBookingRequest::NEW, null, ConfirmBookingRequest::NEW, null);
    }

    /** S7-01: the agenda, a check-in, a consultation or a completion confirm through this command, found by the appointment. */
    public function testFromSchedulingTheOneFileCarryingThePhoneIsTakenAndTheNewPetRegistered(): void
    {
        $this->world->pending(self::SIBLING, createdAt: '2026-11-01 10:00:00');

        self::assertTrue($this->confirmFromScheduling(null, silent: true));

        self::assertSame([[], [[W::CLINIC, W::CLIENT, 'Pixel', 'cat']]], [$this->world->registeredClients, $this->world->registeredPets]);
        self::assertSame([['confirm', W::CLINIC, self::REQUEST, W::CLIENT, W::NEW_PET, 'silent']], $this->world->booking->calls);
        [$link] = array_values($this->world->links->links);
        self::assertSame([W::CLIENT, ClientLinkStatus::CONFIRMED], [$link->clientId(), $link->status()]);
        $request = $this->world->requests->requests[self::REQUEST];
        // Confirmed by Scheduling: nobody answered from « Demandes »
        self::assertSame([BookingRequestStatus::CONFIRMED, W::CLIENT, null, null], [$request->status(), $request->clientId(), $request->resolvedByUserId(), $request->resolvedByName()]);
        self::assertSame(W::CLIENT, $this->world->requests->requests[self::SIBLING]->clientId());
    }

    public function testFromSchedulingNoOrSeveralMatchingFilesOpenANewFileAndTheAppointmentsAnimalOfThatClientIsKept(): void
    {
        $this->world->fakes->mobileMatches[W::PHONE][] = new MobileFileMatch(W::CLINIC, '01960000-0000-7000-8000-0000000c5003', 'Sophie', 'Martin', []);

        self::assertTrue($this->confirmFromScheduling(W::PET));

        // Two files carry the phone: none is guessed, a new one is opened (by the system); Oslo is not that file's
        self::assertSame([[W::CLINIC, 'Sophie', 'Martin', W::PHONE, 'system']], $this->world->registeredClients);
        self::assertSame([['confirm', W::CLINIC, self::REQUEST, W::NEW_FILE, W::NEW_PET]], $this->world->booking->calls);

        // A sibling's file is reused, with the appointment's animal when it is that client's
        $world = new W();
        $world->pending(self::REQUEST)->attachClient(W::CLIENT);
        $world->pets[] = W::pet(W::PET, W::CLIENT, 'Oslo', 'dog');
        self::assertTrue($world->confirmHandler()(ConfirmBookingRequest::forAppointment(W::CLINIC, self::REQUEST, W::PET, false)));
        self::assertSame([[], []], [$world->registeredClients, $world->registeredPets]);
        self::assertSame([['confirm', W::CLINIC, self::REQUEST, W::CLIENT, W::PET]], $world->booking->calls);
    }

    public function testFromSchedulingNoPendingRequestChangesNothing(): void
    {
        // Another appointment, another clinic, or a request already answered
        self::assertFalse($this->world->confirmHandler()(ConfirmBookingRequest::forAppointment(W::CLINIC, '01960000-0000-7000-8000-0000000d0fff', null, false)));
        self::assertFalse($this->world->confirmHandler()(ConfirmBookingRequest::forAppointment(W::CLINIC_2, self::REQUEST, null, false)));
        $this->world->requests->requests[self::REQUEST]->confirm(W::CLIENT, W::utc(W::NOW));
        self::assertFalse($this->confirmFromScheduling(null));

        self::assertSame([[], [], []], [$this->world->booking->calls, $this->world->registeredClients, $this->world->links->links]);

        $this->expectException(\InvalidArgumentException::class);
        $this->world->confirmHandler()(new ConfirmBookingRequest(W::CLINIC, null, ConfirmBookingRequest::DEFAULT, null, ConfirmBookingRequest::DEFAULT, null, null, null));
    }

    private function confirmFromScheduling(?string $animalId, bool $silent = false): bool
    {
        return $this->world->confirmHandler()(ConfirmBookingRequest::forAppointment(W::CLINIC, self::REQUEST, $animalId, $silent));
    }

    private function confirm(string $client, ?string $clientId, string $animal, ?string $animalId): void
    {
        $this->world->confirmHandler()(new ConfirmBookingRequest(W::CLINIC, self::REQUEST, $client, $clientId, $animal, $animalId, W::VET, 'Dr Vet'));
    }
}
