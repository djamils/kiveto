<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Api\Portal;

use App\Context\Animal\Domain\ValueObject\LifeStatus;
use App\Context\Animal\Domain\ValueObject\TransferStatus;
use App\Context\ClientPortal\Domain\Link\ClientLink;
use App\Context\ClientPortal\Domain\Link\ClientLinkId;
use App\Context\ClientPortal\Domain\Link\ClientLinkRepositoryInterface;
use App\Context\Scheduling\Domain\Booking\BookingPreventiveCareKind;
use App\Context\Scheduling\Domain\Booking\ConfirmationMode;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockType;
use App\Fixtures\System\IdentityAccess\Factory\PortalUserFactory;
use App\Presentation\Api\Shared\Idempotency\IdempotentProcessor;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

/**
 * Slice 4: online booking through the Portal (AC 44–47, 147, 151). Today is
 * Monday 2 November 2026, 11:00 in Paris (10:00 UTC); the veterinarian
 * consults on Tuesday 3 November 09:00–12:30.
 */
final class AppointmentBookingTest extends BookingApiTestCase
{
    private const string TUESDAY_9   = '2026-11-03T09:00:00+01:00';
    private const string TUESDAY_11  = '2026-11-03T11:00:00+01:00';
    private const string OTHER_PHONE = '+33615000415';

    private string $vet;

    private string $vaccination;

    private string $rex;

    protected function setUp(): void
    {
        $this->clientIp = '203.0.113.93';
        parent::setUp();

        $this->vet         = $this->veterinarian('Claire', 'Rousseau');
        $this->vaccination = $this->motif('Rappel vaccin', [$this->vet], BookingPreventiveCareKind::VACCINATION);
        $this->rex         = $this->pet('Rex');
        $this->block($this->vet, PlanningBlockType::CONSULTATION, '2026-11-03', '09:00', '12:30');
        $this->enableOnlineBooking();
    }

    public function testAnAutoConfirmedMotifBooksAConfirmedPortalAppointment(): void
    {
        $answer = self::json($this->book($this->body(), 'key-auto'), 201);

        $id = self::string($answer['id']);
        self::assertSame(
            [
                'id'                  => $id,
                'startAt'             => self::TUESDAY_9,
                'endAt'               => '2026-11-03T09:30:00+01:00',
                'pet'                 => ['id' => $this->rex, 'name' => 'Rex'],
                'motif'               => ['id' => $this->vaccination, 'label' => 'Rappel vaccin'],
                'practitioner'        => ['id' => $this->vet, 'displayName' => 'Dr Claire Rousseau'],
                'status'              => 'confirmed',
                'source'              => 'portal',
                'cancellableUntil'    => '2026-11-02T11:15:00+01:00',
                'questionnaireStatus' => 'not_started',
                'relatedCareItem'     => null,
            ],
            $answer,
        );
        self::assertSame('Rappel vaccin', $this->connection()->fetchOne('SELECT reason FROM scheduling__appointments WHERE id = UUID_TO_BIN(?)', [$id]), 'the agenda reason is the motif label');
        self::assertSame(1, $this->queuedSyncs($id), 'the confirmation message is on its way');
    }

    public function testAManualMotifBooksAPendingRequestWithoutMessageAndOnlyBeyondTheManualLeadTime(): void
    {
        $manual = $this->motif('Bilan', [$this->vet], confirmation: ConfirmationMode::MANUAL);

        self::assertSame('slot_taken', self::problemType($this->book($this->body(motifId: $manual), 'key-early'), 409), 'within 24 h');

        $answer = self::json($this->book($this->body(motifId: $manual, startAt: self::TUESDAY_11), 'key-manual'), 201);
        $id     = self::string($answer['id']);

        self::assertSame(['pending_clinic_confirmation', 'portal'], [$answer['status'], $answer['source']]);
        self::assertSame(0, $this->queuedSyncs($id), 'a pending request sends nothing');
        $expiresAt = $this->connection()->fetchOne('SELECT pending_expires_at FROM scheduling__appointments WHERE id = UUID_TO_BIN(?)', [$id]);
        self::assertIsString($expiresAt);
        self::assertGreaterThan(self::NOW, $expiresAt, 'not declined at creation (AC 147)');
    }

    public function testTwoOwnersOnOneSlotGiveOneBookingAndAReplayAnswersTheSameWithoutASecondAppointment(): void
    {
        $winner = $this->book($this->body(), 'key-winner');
        $body   = self::json($winner, 201);

        [$token, $pet] = $this->otherOwner();
        $loser         = $this->book($this->body(petId: $pet), 'key-loser', $token);
        self::assertSame('slot_taken', self::problemType($loser, 409));

        $replay = $this->book($this->body(), 'key-winner');
        self::assertSame(201, $replay->getStatusCode());
        self::assertSame('true', $replay->headers->get(IdempotentProcessor::REPLAYED_HEADER));
        self::assertSame(self::canonical($body), self::canonical(self::json($replay, 201)), 'the same body, its keys as stored');
        self::assertSame(1, $this->appointmentsAt('2026-11-03 08:00:00'));

        self::assertSame('slot_taken', self::problemType($this->book($this->body(petId: $pet), 'key-loser', $token), 409), 'the losing request runs again');
    }

    public function testTheCreateRoutesRequireAnIdempotencyKey(): void
    {
        self::assertSame('idempotency_key_missing', self::problemType($this->book($this->body(), null), 400));
        self::assertSame('idempotency_key_missing', self::problemType($this->authPost(self::clinicPath('/appointments/with-new-pet'), $this->newPetBody('Plume', 'cat')), 400));
        self::assertSame(0, $this->appointmentsAt('2026-11-03 08:00:00'));
    }

    public function testARetryUnderANewKeyAfterALostAnswerReturnsTheBookingAlreadyMade(): void
    {
        $first = self::json($this->book($this->body(), 'key-lost'), 201);

        // The answer was committed but its key never written: the app retries with a new one.
        $retry = self::json($this->book($this->body(), 'key-retry'), 201);

        self::assertSame($first, $retry);
        self::assertSame(1, $this->appointmentsAt('2026-11-03 08:00:00'));
    }

    public function testWithANewPetTheAnimalIsRegisteredWithItsDefaultsAndBooked(): void
    {
        $answer = self::json($this->authPost(self::clinicPath('/appointments/with-new-pet'), $this->newPetBody(' Plume ', 'cat'), 'key-new-pet'), 201);

        $pet = $answer['pet'] ?? null;
        self::assertIsArray($pet);
        self::assertSame('Plume', $pet['name']);
        self::assertSame(['confirmed', 'portal'], [$answer['status'], $answer['source']]);

        $animal = $this->connection()->fetchAssociative(
            'SELECT a.species, a.sex, a.reproductive_status, a.registry_type, a.life_status, a.transfer_status, a.is_mixed_breed, BIN_TO_UUID(o.client_id) AS owner, o.role
             FROM animal__animals a JOIN animal__ownerships o ON o.animal_id = a.id WHERE a.id = UUID_TO_BIN(?)',
            [self::string($pet['id'])],
        );
        self::assertSame(
            ['species' => 'cat', 'sex' => 'unknown', 'reproductive_status' => 'unknown', 'registry_type' => 'none', 'life_status' => 'alive', 'transfer_status' => 'none', 'is_mixed_breed' => 0, 'owner' => self::CLIENT, 'role' => 'primary'],
            $animal,
        );
    }

    public function testAWithNewPetRetryOfTheSameRequestReusesTheAnimalAndTheBooking(): void
    {
        $first = self::json($this->authPost(self::clinicPath('/appointments/with-new-pet'), $this->newPetBody('Plume', 'cat'), 'key-new-1'), 201);
        // Its stored answer lost (the booking committed, the key's record did not): the same key runs it again
        $this->connection()->executeStatement('DELETE FROM shared__idempotency_keys WHERE idempotency_key = ?', ['key-new-1']);
        $retry = self::json($this->authPost(self::clinicPath('/appointments/with-new-pet'), $this->newPetBody('Plume', 'cat'), 'key-new-1'), 201);

        self::assertSame($first, $retry);
        self::assertSame(1, $this->animalsNamed('Plume'));
        self::assertSame(1, $this->appointmentsAt('2026-11-03 08:00:00'));

        // S4B-01: another request (another key) never reuses an animal by its name: its own booking of that slot is refused, no animal left
        self::assertSame('slot_taken', self::problemType($this->authPost(self::clinicPath('/appointments/with-new-pet'), $this->newPetBody('Plume', 'cat'), 'key-new-2'), 409));
        self::assertSame(1, $this->animalsNamed('Plume'));
    }

    public function testAWithNewPetBookingOnATakenSlotLeavesNoAnimal(): void
    {
        $this->appointment($this->rex, '2026-11-03 08:00:00', practitionerId: $this->vet);

        self::assertSame('slot_taken', self::problemType($this->authPost(self::clinicPath('/appointments/with-new-pet'), $this->newPetBody('Plume', 'cat'), 'key-taken'), 409));
        self::assertSame(0, $this->animalsNamed('Plume'));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function provideAnInvalidNewPetIsRefusedCases(): iterable
    {
        yield 'unknown species' => [['name' => 'Plume', 'species' => 'horse'], 'newPet.species'];
        yield 'blank name' => [['name' => '  ', 'species' => 'cat'], 'newPet.name'];
        yield 'name too long' => [['name' => str_repeat('a', 81), 'species' => 'cat'], 'newPet.name'];
    }

    /**
     * @param array<string, mixed> $newPet
     */
    #[DataProvider('provideAnInvalidNewPetIsRefusedCases')]
    public function testAnInvalidNewPetIsRefused(array $newPet, string $path): void
    {
        $problem = self::json($this->authPost(self::clinicPath('/appointments/with-new-pet'), [...$this->newPetBody('x', 'cat'), 'newPet' => $newPet], 'key-invalid'), 422);

        self::assertSame(['validation_failed', $path], [$problem['type'], self::firstViolationPath($problem)]);
        self::assertSame(0, $this->animalsNamed('Plume'));
    }

    public function testADeceasedOrMissingPetIsNotBookable(): void
    {
        $ghost = $this->pet('Anubis', attributes: ['lifeStatus' => LifeStatus::DECEASED, 'deceasedAt' => new \DateTimeImmutable('2026-06-01')]);
        $lost  = $this->pet('Bandit', attributes: ['lifeStatus' => LifeStatus::MISSING, 'missingSince' => new \DateTimeImmutable('2026-08-01')]);

        self::assertSame('pet_not_bookable', self::problemType($this->book($this->body(petId: $ghost), 'key-ghost'), 422));
        self::assertSame('pet_not_bookable', self::problemType($this->book($this->body(petId: $lost), 'key-lost'), 422));
    }

    public function testAPetOfAnotherClientOrSoldOrGivenIsNotFound(): void
    {
        $pets = [
            $this->pet('Stranger', self::OTHER_CLIENT),
            $this->pet('Vendu', attributes: ['transferStatus' => TransferStatus::SOLD, 'soldAt' => new \DateTimeImmutable('2026-09-01')]),
            $this->pet('Donné', attributes: ['transferStatus' => TransferStatus::GIVEN, 'givenAt' => new \DateTimeImmutable('2026-09-01')]),
            Uuid::v7()->toRfc4122(),
        ];

        foreach ($pets as $i => $petId) {
            self::assertSame('not_found', self::problemType($this->book($this->body(petId: $petId), 'key-pet-' . $i), 404), $petId);
        }
    }

    public function testBookingIsNotOpenWhenDisabledOrForAMotifNotOnline(): void
    {
        $offline = $this->motif('Chirurgie', [$this->vet], online: false);
        self::assertSame('booking_not_open', self::problemType($this->book($this->body(motifId: $offline), 'key-offline'), 422));

        $this->enableOnlineBooking(false);
        self::assertSame('booking_not_open', self::problemType($this->book($this->body(), 'key-disabled'), 422));
        self::assertSame('booking_not_open', self::problemType($this->authPost(self::clinicPath('/appointments/with-new-pet'), $this->newPetBody('Plume', 'cat'), 'key-disabled-new'), 422));
        self::assertSame(0, $this->animalsNamed('Plume'));
    }

    public function testAnUnknownMotifIsNotFound(): void
    {
        self::assertSame('not_found', self::problemType($this->book($this->body(motifId: Uuid::v7()->toRfc4122()), 'key-motif'), 404));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function provideAMalformedBookingIsRefusedCases(): iterable
    {
        yield 'no pet' => [['petId' => ''], 'petId'];
        yield 'pet not a uuid' => [['petId' => 'rex'], 'petId'];
        yield 'motif not a uuid' => [['motifId' => 'vaccin'], 'motifId'];
        yield 'practitioner not a uuid' => [['practitionerId' => 'claire'], 'practitionerId'];
        yield 'start without offset' => [['startAt' => '2026-11-03T09:00:00'], 'startAt'];
        yield 'start not a date' => [['startAt' => 'mardi'], 'startAt'];
    }

    /**
     * @param array<string, mixed> $override
     */
    #[DataProvider('provideAMalformedBookingIsRefusedCases')]
    public function testAMalformedBookingIsRefused(array $override, string $path): void
    {
        $problem = self::json($this->book([...$this->body(), ...$override], 'key-malformed'), 422);

        self::assertSame(['validation_failed', $path], [$problem['type'], self::firstViolationPath($problem)]);
    }

    public function testAStartInUtcIsTheSameInstant(): void
    {
        $answer = self::json($this->book($this->body(startAt: '2026-11-03T08:00:00Z'), 'key-utc'), 201);

        self::assertSame(self::TUESDAY_9, $answer['startAt']);
    }

    public function testTheCreateRoutesAreHiddenFromAClinicWithoutConfirmedLink(): void
    {
        $this->enableOnlineBooking(clinicId: self::OTHER_CLINIC);

        self::assertSame('not_found', self::problemType($this->authPost(self::clinicPath('/appointments', self::OTHER_CLINIC), $this->body(), 'key-tenant'), 404));
        self::assertSame('not_found', self::problemType($this->authPost(self::clinicPath('/appointments/with-new-pet', self::OTHER_CLINIC), $this->newPetBody('Plume', 'cat'), 'key-tenant-new'), 404));
        self::assertSame(0, $this->animalsNamed('Plume'));
    }

    protected function phone(): string
    {
        return '+33615000404';
    }

    /**
     * @return array<string, mixed>
     */
    private function body(?string $petId = null, ?string $motifId = null, string $startAt = self::TUESDAY_9): array
    {
        return [
            'petId'          => $petId ?? $this->rex,
            'motifId'        => $motifId ?? $this->vaccination,
            'practitionerId' => $this->vet,
            'startAt'        => $startAt,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function newPetBody(string $name, string $species): array
    {
        return [
            'newPet'         => ['name' => $name, 'species' => $species],
            'motifId'        => $this->vaccination,
            'practitionerId' => $this->vet,
            'startAt'        => self::TUESDAY_9,
        ];
    }

    /**
     * @param array<string, mixed> $body
     */
    private function book(array $body, ?string $idempotencyKey, ?string $token = null): Response
    {
        return $this->authPost(self::clinicPath('/appointments'), $body, $idempotencyKey, $token);
    }

    /**
     * Another account, linked to another client file of the clinic, with a
     * pet of its own.
     *
     * @return array{string, string} its access token and its pet id
     */
    private function otherOwner(): array
    {
        PortalUserFactory::new()->withPhone(self::OTHER_PHONE)->create();
        $token  = self::string($this->signIn(self::OTHER_PHONE)['accessToken']);
        $answer = self::json($this->get('/me', ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]), 200)['account'] ?? null;
        self::assertIsArray($answer);

        $this->service(ClientLinkRepositoryInterface::class)->save(
            ClientLink::confirm(ClientLinkId::fromString(Uuid::v7()->toRfc4122()), self::string($answer['id']), self::CLINIC, self::OTHER_CLIENT, new \DateTimeImmutable('2026-10-01')),
        );

        return [$token, $this->pet('Nala', self::OTHER_CLIENT)];
    }

    /**
     * A replayed body comes back from a JSON column: same members, keys
     * reordered.
     *
     * @param array<mixed> $body
     *
     * @return array<mixed>
     */
    private static function canonical(array $body): array
    {
        ksort($body);

        return array_map(static fn (mixed $value): mixed => \is_array($value) ? self::canonical($value) : $value, $body);
    }

    private function appointmentsAt(string $startsAtUtc): int
    {
        return $this->number('SELECT COUNT(*) FROM scheduling__appointments WHERE clinic_id = UUID_TO_BIN(?) AND starts_at_utc = ?', [self::CLINIC, $startsAtUtc]);
    }

    private function animalsNamed(string $name): int
    {
        return $this->number('SELECT COUNT(*) FROM animal__animals WHERE clinic_id = UUID_TO_BIN(?) AND name = ?', [self::CLINIC, $name]);
    }

    /** The client-message syncs queued for the appointment. */
    private function queuedSyncs(string $appointmentId): int
    {
        return $this->number('SELECT COUNT(*) FROM shared__messenger_messages WHERE body LIKE ? AND body LIKE ?', ['%SyncAppointmentNotification%', '%' . $appointmentId . '%']);
    }
}
