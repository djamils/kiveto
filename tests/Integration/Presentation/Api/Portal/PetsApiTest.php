<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Api\Portal;

use App\Context\Animal\Domain\ValueObject\AnimalStatus;
use App\Context\Animal\Domain\ValueObject\LifeStatus;
use App\Context\Animal\Domain\ValueObject\PreventiveCareKind;
use App\Context\Animal\Domain\ValueObject\ReproductiveStatus;
use App\Context\Animal\Domain\ValueObject\Sex;
use App\Context\Animal\Domain\ValueObject\Species;
use App\Context\Animal\Domain\ValueObject\TransferStatus;
use App\Context\Consultation\Domain\ValueObject\ConsultationStatus;
use App\Fixtures\Context\Animal\Factory\WeightRecordEntityFactory;
use App\Fixtures\Context\Consultation\Factory\ConsultationEntityFactory;
use App\Fixtures\Context\Patient\Factory\PatientEntityFactory;
use App\Fixtures\Context\Scheduling\Factory\AppointmentEntityFactory;
use Symfony\Component\Uid\Uuid;

/**
 * Slice 3: the pet list, the pet detail and its care items (AC 18 to 22,
 * 110, 111). Today is 2 November 2026 (frozen clock).
 */
final class PetsApiTest extends PetsApiTestCase
{
    private const string THIRD_CLIENT = '01930000-0000-7000-8000-00000000d303';

    protected function setUp(): void
    {
        $this->clientIp = '203.0.113.81';
        parent::setUp();
    }

    public function testWithoutAConfirmedLinkEveryPetRouteAnswersNotFound(): void
    {
        $pet = $this->pet('Rex', self::OTHER_CLIENT, clinicId: self::OTHER_CLINIC);

        foreach (['/pets', '/pets/' . $pet, '/pets/' . $pet . '/care-items', '/pets/' . $pet . '/timeline', '/pets/' . $pet . '/vaccination-booklet', '/home'] as $route) {
            self::assertSame('not_found', self::problemType($this->authGet('/clinics/' . self::OTHER_CLINIC . $route), 404), $route);
        }
        self::assertSame('not_found', self::problemType($this->authGet('/clinics/not-a-uuid/pets'), 404));
    }

    public function testThePetRoutesNeedABearerToken(): void
    {
        self::assertSame(401, $this->get('/clinics/' . self::CLINIC . '/pets')->getStatusCode());
    }

    public function testTheListMapsTheDomainToTheContractAliveFirstThenByName(): void
    {
        $milo = $this->pet('milo', attributes: [
            'species'            => Species::CAT,
            'breedName'          => 'Européen',
            'sex'                => Sex::MALE,
            'reproductiveStatus' => ReproductiveStatus::NEUTERED,
            'birthDate'          => new \DateTimeImmutable('2020-03-14'),
        ]);
        $eclair = $this->pet('éclair', attributes: [
            'species'            => Species::DOG,
            'isMixedBreed'       => true,
            'sex'                => Sex::FEMALE,
            'reproductiveStatus' => ReproductiveStatus::INTACT,
        ]);
        $zut   = $this->pet('Zut', attributes: ['species' => Species::NAC, 'sex' => Sex::UNKNOWN, 'reproductiveStatus' => ReproductiveStatus::UNKNOWN]);
        $ghost = $this->pet('Anubis', attributes: ['lifeStatus' => LifeStatus::DECEASED, 'deceasedAt' => new \DateTimeImmutable('2026-06-01')]);
        $lost  = $this->pet('Bandit', attributes: ['lifeStatus' => LifeStatus::MISSING, 'missingSince' => new \DateTimeImmutable('2026-08-01')]);

        $pets = self::list($this->authGet('/clinics/' . self::CLINIC . '/pets'));

        self::assertSame([$eclair, $milo, $zut, $ghost, $lost], array_column($pets, 'id'));
        self::assertSame([
            'id'            => $milo,
            'name'          => 'milo',
            'species'       => 'cat',
            'breed'         => 'Européen',
            'sex'           => 'male',
            'sterilization' => 'neutered',
            'birthDate'     => '2020-03-14',
            'initial'       => 'M',
            'lifeStatus'    => 'alive',
        ], $pets[1]);
        self::assertSame(['Croisé', 'É', 'female', 'intact', null], [$pets[0]['breed'], $pets[0]['initial'], $pets[0]['sex'], $pets[0]['sterilization'], $pets[0]['birthDate']]);
        self::assertSame([null, 'nac', 'unknown', 'unknown'], [$pets[2]['breed'], $pets[2]['species'], $pets[2]['sex'], $pets[2]['sterilization']]);
        self::assertSame(['deceased', 'missing'], [$pets[3]['lifeStatus'], $pets[4]['lifeStatus']]);

        $detail = self::json($this->authGet('/clinics/' . self::CLINIC . '/pets/' . $ghost), 200);
        self::assertSame('deceased', $detail['lifeStatus']);
        self::assertSame([], self::list($this->authGet('/clinics/' . self::CLINIC . '/pets/' . $ghost . '/care-items')));
        self::assertSame([], self::list($this->authGet('/clinics/' . self::CLINIC . '/pets/' . $lost . '/care-items')));
    }

    public function testExcludedPetsAreAbsentAndAnswerNotFoundOnEveryRoute(): void
    {
        $archived  = $this->pet('Archivé', attributes: ['status' => AnimalStatus::ARCHIVED]);
        $sold      = $this->pet('Vendu', attributes: ['transferStatus' => TransferStatus::SOLD, 'soldAt' => new \DateTimeImmutable('2026-09-01')]);
        $given     = $this->pet('Donné', attributes: ['transferStatus' => TransferStatus::GIVEN, 'givenAt' => new \DateTimeImmutable('2026-09-01')]);
        $secondary = $this->pet('Copropriété', primary: false);
        $foreign   = $this->pet('Voisin', self::OTHER_CLIENT);

        self::assertSame([], self::list($this->authGet('/clinics/' . self::CLINIC . '/pets')));

        foreach ([$archived, $sold, $given, $secondary, $foreign] as $pet) {
            foreach (['', '/care-items', '/timeline', '/vaccination-booklet'] as $route) {
                self::assertSame('not_found', self::problemType($this->authGet('/clinics/' . self::CLINIC . '/pets/' . $pet . $route), 404), $route);
            }
        }
        self::assertSame('not_found', self::problemType($this->authGet('/clinics/' . self::CLINIC . '/pets/' . Uuid::v7()->toRfc4122()), 404));
    }

    public function testTheDetailCarriesTheLatestWeightTheMaskedChipAndTheReferentVet(): void
    {
        $pet = $this->pet('Rex', attributes: ['microchipNumber' => '250269812345678']);
        WeightRecordEntityFactory::new()->forAnimal(self::CLINIC, $pet)->weighing(11_900, new \DateTimeImmutable('2026-03-01'))->create();
        $this->consultationWeight($pet, '12.400', '2026-03-10 09:00:00', ConsultationStatus::CLOSED);
        $this->consultationWeight($pet, '13.100', '2026-03-15 09:00:00', ConsultationStatus::OPEN);
        $this->report(self::CLIENT, '2026-03-01 10:00:00', [$pet], vet: 'Dr Ancien');
        $this->report(self::CLIENT, '2026-03-10 10:00:00', [$pet], vet: 'Dr Claire Lambert');
        $this->report(self::OTHER_CLIENT, '2026-03-12 10:00:00', [$pet], vet: 'Dr Autre Client');

        $detail = self::json($this->authGet('/clinics/' . self::CLINIC . '/pets/' . $pet), 200);

        self::assertSame(['weightGrams' => 12400, 'at' => '2026-03-10'], $detail['lastWeight']);
        self::assertSame('•••• 5678', $detail['chipNumberMasked']);
        self::assertSame('Dr Claire Lambert', $detail['referentVet']);
        self::assertSame(['id', 'name', 'species', 'breed', 'sex', 'sterilization', 'birthDate', 'initial', 'lifeStatus', 'lastWeight', 'chipNumberMasked', 'referentVet'], array_keys($detail));
    }

    public function testOnTheSameLocalDayTheAnimalRecordWinsAndNothingGivesNulls(): void
    {
        $pet = $this->pet('Rex');
        $this->consultationWeight($pet, '12.400', '2026-03-10 07:00:00', ConsultationStatus::CLOSED);
        WeightRecordEntityFactory::new()->forAnimal(self::CLINIC, $pet)->weighing(11_900, new \DateTimeImmutable('2026-03-10'))->create();
        $bare = $this->pet('Nu');

        self::assertSame(['weightGrams' => 11900, 'at' => '2026-03-10'], self::json($this->authGet('/clinics/' . self::CLINIC . '/pets/' . $pet), 200)['lastWeight']);

        $detail = self::json($this->authGet('/clinics/' . self::CLINIC . '/pets/' . $bare), 200);
        self::assertNull($detail['lastWeight']);
        self::assertNull($detail['chipNumberMasked']);
        self::assertNull($detail['referentVet']);
    }

    public function testCareItemsCarryTheirStatusNearestDueDateFirstAndNeverBooked(): void
    {
        $pet = $this->pet('Rex');
        $this->careAct($pet, PreventiveCareKind::VACCINATION, 'CHPPiL', '2025-12-31', '2027-01-01');
        $this->careAct($pet, PreventiveCareKind::INTERNAL_PARASITE, 'Milbemax', '2026-08-01', '2026-11-01');
        $this->careAct($pet, PreventiveCareKind::EXTERNAL_PARASITE, 'Bravecto', '2026-08-22', '2026-11-22');
        // Renewed without a due date: the older act's due date never resurfaces.
        $this->careAct($pet, PreventiveCareKind::VACCINATION, 'Rage', '2025-06-01', '2026-06-01');
        $this->careAct($pet, PreventiveCareKind::VACCINATION, 'rage', '2026-06-01', null);
        // Only the latest act of a protocol counts, the label read without case.
        $this->careAct($pet, PreventiveCareKind::VACCINATION, 'leucose', '2025-01-01', '2025-02-01');
        $this->careAct($pet, PreventiveCareKind::VACCINATION, 'Leucose', '2026-10-01', '2027-10-01');
        AppointmentEntityFactory::new()->withClinicId(self::CLINIC)->startingAt(new \DateTimeImmutable('2026-11-05 09:00:00'))
            ->afterInstantiate(static function (\App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Entity\AppointmentEntity $appointment) use ($pet): void {
                $appointment->setAnimalId(Uuid::fromString($pet));
                $appointment->setReason('Vermifuge');
            })->create()
        ;

        $items = self::list($this->authGet('/clinics/' . self::CLINIC . '/pets/' . $pet . '/care-items'));

        self::assertSame([
            ['kind' => 'internal_parasite', 'label' => 'Milbemax', 'lastDoneOn' => '2026-08-01', 'dueOn' => '2026-11-01', 'status' => 'overdue'],
            ['kind' => 'external_parasite', 'label' => 'Bravecto', 'lastDoneOn' => '2026-08-22', 'dueOn' => '2026-11-22', 'status' => 'due_soon'],
            ['kind' => 'vaccination', 'label' => 'CHPPiL', 'lastDoneOn' => '2025-12-31', 'dueOn' => '2027-01-01', 'status' => 'up_to_date'],
            ['kind' => 'vaccination', 'label' => 'Leucose', 'lastDoneOn' => '2026-10-01', 'dueOn' => '2027-10-01', 'status' => 'up_to_date'],
        ], $items);
    }

    public function testTodayIsTheClinicLocalDayAndDatesAreNeverShifted(): void
    {
        $pet = $this->pet('Rex', attributes: ['birthDate' => new \DateTimeImmutable('2021-01-01')]);
        $this->careAct($pet, PreventiveCareKind::VACCINATION, 'CHPPiL', '2025-11-01', '2026-11-01');
        $this->careAct($pet, PreventiveCareKind::INTERNAL_PARASITE, 'Milbemax', '2026-08-05', '2026-11-05');
        $items = '/clinics/' . self::CLINIC . '/pets/' . $pet . '/care-items';

        // 23:30 UTC on 1 November is still 1 November in London, already 2 November in Nouméa (UTC+11).
        $this->travelTo('2026-11-01 23:30:00');
        $this->clinicIn('Europe/London');
        self::assertSame('due_soon', self::list($this->authGet($items))[0]['status']);
        $this->clinicIn('Pacific/Noumea');
        self::assertSame('overdue', self::list($this->authGet($items))[0]['status']);

        $this->clinicIn('America/Martinique');
        $listed = self::list($this->authGet($items));
        self::assertSame(['2026-11-01', '2026-11-05'], array_column($listed, 'dueOn'));
        self::assertSame(['2025-11-01', '2026-08-05'], array_column($listed, 'lastDoneOn'));
        self::assertSame('2021-01-01', self::list($this->authGet('/clinics/' . self::CLINIC . '/pets'))[0]['birthDate']);
    }

    public function testAnOwnerLinkedToTwoClientFilesSeesThePetsOfBoth(): void
    {
        $this->link(self::OTHER_CLIENT);
        $mine     = $this->pet('Alpha');
        $other    = $this->pet('Bravo', self::OTHER_CLIENT);
        $stranger = $this->pet('Charlie', self::THIRD_CLIENT);
        $this->careAct($other, PreventiveCareKind::VACCINATION, 'CHPPiL', '2025-12-01', '2026-12-01');

        self::assertSame([$mine, $other], array_column(self::list($this->authGet('/clinics/' . self::CLINIC . '/pets')), 'id'));
        self::assertCount(1, self::list($this->authGet('/clinics/' . self::CLINIC . '/pets/' . $other . '/care-items')));
        self::assertSame('not_found', self::problemType($this->authGet('/clinics/' . self::CLINIC . '/pets/' . $stranger), 404));
    }

    public function testARehomedPetShowsTheNewOwnerOnlyItsReportsAndTheOldOwnerNothing(): void
    {
        // Re-homed to the caller's client: the previous owner's reports stay hidden.
        $adopted = $this->pet('Adopté');
        $this->endedOwnership($adopted, self::OTHER_CLIENT);
        $this->report(self::OTHER_CLIENT, '2026-05-01 10:00:00', [$adopted], vet: 'Dr Ancien Foyer');

        $detail = self::json($this->authGet('/clinics/' . self::CLINIC . '/pets/' . $adopted), 200);
        self::assertNull($detail['referentVet']);

        $visit = Uuid::v7()->toRfc4122();
        $this->report(self::CLIENT, '2026-04-01 10:00:00', [$adopted], $visit, 'Dr Nouveau Foyer');
        self::assertSame('Dr Nouveau Foyer', self::json($this->authGet('/clinics/' . self::CLINIC . '/pets/' . $adopted), 200)['referentVet']);
        $timeline = self::rows(self::json($this->authGet('/clinics/' . self::CLINIC . '/pets/' . $adopted . '/timeline'), 200)['items']);
        self::assertSame([$visit], array_column($timeline, 'visitId'));
        self::assertSame(['Dr Nouveau Foyer'], array_column($timeline, 'vet'));

        // Re-homed away from the caller's client: gone.
        $given = $this->pet('Parti', self::OTHER_CLIENT);
        $this->endedOwnership($given, self::CLIENT);

        self::assertSame([$adopted], array_column(self::list($this->authGet('/clinics/' . self::CLINIC . '/pets')), 'id'));
        self::assertSame('not_found', self::problemType($this->authGet('/clinics/' . self::CLINIC . '/pets/' . $given), 404));
        self::assertSame('not_found', self::problemType($this->authGet('/clinics/' . self::CLINIC . '/pets/' . $given . '/timeline'), 404));
    }

    protected function phone(): string
    {
        return '+33615000301';
    }

    private function clinicIn(string $timeZone): void
    {
        $this->connection()->update('clinic__clinics', ['time_zone' => $timeZone], ['id' => Uuid::fromString(self::CLINIC)->toBinary()]);
    }

    private function endedOwnership(string $petId, string $clientId): void
    {
        $animal = \App\Fixtures\Context\Animal\Factory\AnimalEntityFactory::repository()->find(['id' => Uuid::fromString($petId)]);
        self::assertNotNull($animal);
        \App\Fixtures\Context\Animal\Factory\OwnershipEntityFactory::new()->withClientId($clientId)->primary()->ended()->create(['animal' => $animal]);
    }

    private function consultationWeight(string $petId, string $weightKg, string $startedAtUtc, ConsultationStatus $status): void
    {
        $patient = Uuid::v7()->toRfc4122();
        PatientEntityFactory::new()->withId($patient)->withClinicId(self::CLINIC)->identified()->withAnimalLinkId($petId)->create();
        $startedAt = new \DateTimeImmutable($startedAtUtc, new \DateTimeZone('UTC'));

        ConsultationEntityFactory::new()->withClinicId(self::CLINIC)->withPatientId($patient)->withStatus($status)->create([
            'weightKg'     => $weightKg,
            'startedAtUtc' => $startedAt,
            'closedAtUtc'  => ConsultationStatus::CLOSED === $status ? $startedAt->modify('+30 minutes') : null,
        ]);
    }
}
