<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Infrastructure\Adapter;

use App\Context\Animal\Application\Query\GetAnimalById\AnimalView;
use App\Context\Animal\Application\Query\GetAnimalById\GetAnimalById;
use App\Context\Animal\Application\Query\GetAnimalById\IdentificationDto;
use App\Context\Animal\Application\Query\GetAnimalById\LifeCycleDto;
use App\Context\Animal\Application\Query\GetAnimalById\TransferDto;
use App\Context\Animal\Application\Query\ListAnimalPreventiveCare\ListAnimalPreventiveCare;
use App\Context\Animal\Application\Query\ListAnimalPreventiveCare\PreventiveCareRecordView;
use App\Context\Animal\Application\Query\ListAnimalWeightRecords\ListAnimalWeightRecords;
use App\Context\Animal\Application\Query\ListAnimalWeightRecords\WeightRecordView;
use App\Context\Animal\Application\Query\ListLatestPreventiveCare\LatestPreventiveCareView;
use App\Context\Animal\Application\Query\ListLatestPreventiveCare\ListLatestPreventiveCare;
use App\Context\Animal\Application\Query\ListPortalPets\ListPortalPets;
use App\Context\Animal\Application\Query\ListPortalPets\PortalPetView;
use App\Context\ClientPortal\Application\Port\PreventiveAct;
use App\Context\ClientPortal\Application\Port\VaccinationRecord;
use App\Context\ClientPortal\Infrastructure\Adapter\Animal\QueryBusAnimalWeights;
use App\Context\ClientPortal\Infrastructure\Adapter\Animal\QueryBusPetDirectory;
use App\Context\ClientPortal\Infrastructure\Adapter\Animal\QueryBusPreventiveCare;
use App\Context\ClientPortal\Infrastructure\Adapter\Animal\QueryBusVaccinationRecords;
use App\Context\ClientPortal\Infrastructure\Adapter\Clinic\QueryBusClinicProfile;
use App\Context\ClientPortal\Infrastructure\Adapter\Clinic\QueryBusPractitionerNames;
use App\Context\ClientPortal\Infrastructure\Adapter\Consultation\QueryBusConsultationWeights;
use App\Context\ClientPortal\Infrastructure\Adapter\Consultation\QueryBusVisitSummaries;
use App\Context\ClientPortal\Infrastructure\Adapter\Scheduling\QueryBusOwnerAppointments;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinic;
use App\Context\Clinic\Application\Query\Staff\GetStaffPrintedName\GetStaffPrintedName;
use App\Context\Clinic\Domain\ValueObject\ClinicStatus;
use App\Context\Consultation\Application\Query\GetConsultationPortalSummaries\ConsultationPortalSummary;
use App\Context\Consultation\Application\Query\GetConsultationPortalSummaries\GetConsultationPortalSummaries;
use App\Context\Consultation\Application\Query\GetLatestClosedConsultationWeight\ConsultationWeight;
use App\Context\Consultation\Application\Query\GetLatestClosedConsultationWeight\GetLatestClosedConsultationWeight;
use App\Context\Scheduling\Application\Query\GetNextAppointmentForAnimals\GetNextAppointmentForAnimals;
use App\Context\Scheduling\Application\Query\GetNextAppointmentForAnimals\NextAppointmentItem;
use App\Tests\Support\AnsweringQueryBus;
use PHPUnit\Framework\TestCase;

final class PetQueryBusAdaptersTest extends TestCase
{
    public function testTheVisiblePetsAreAnimals(): void
    {
        $view = new PortalPetView('a1', 'client-1', 'Moka', 'cat', null, true, 'female', 'neutered', new \DateTimeImmutable('2020-05-01'), 'alive', '250269812345678');
        $bus  = new AnsweringQueryBus([ListPortalPets::class => [$view]]);

        $pets = new QueryBusPetDirectory($bus)->visiblePets('clinic-1', ['client-1', 'client-2']);

        self::assertCount(1, $pets);
        self::assertSame(
            ['a1', 'client-1', 'Moka', 'cat', null, true, 'female', 'neutered', '2020-05-01', 'alive', '250269812345678', true],
            [$pets[0]->id, $pets[0]->primaryOwnerClientId, $pets[0]->name, $pets[0]->species, $pets[0]->breedName, $pets[0]->isMixedBreed, $pets[0]->sex, $pets[0]->reproductiveStatus, $pets[0]->birthDate?->format('Y-m-d'), $pets[0]->lifeStatus, $pets[0]->microchipNumber, $pets[0]->isAlive()],
        );
        $query = $bus->asked[0];
        self::assertInstanceOf(ListPortalPets::class, $query);
        self::assertSame(['clinic-1', ['client-1', 'client-2']], [$query->clinicId, $query->clientIds]);

        self::assertSame([], new QueryBusPetDirectory($bus)->visiblePets('clinic-1', []));
        self::assertCount(1, $bus->asked, 'no client, no query');
        self::assertSame([], new QueryBusPetDirectory(new AnsweringQueryBus())->visiblePets('clinic-1', ['client-1']));
    }

    public function testTheIdentityOfAnAnimalIsRead(): void
    {
        $animal = static fn (?string $birthDate): AnimalView => new AnimalView(
            'a1',
            'clinic-1',
            'Moka',
            'cat',
            'female',
            'neutered',
            true,
            null,
            $birthDate,
            null,
            null,
            new IdentificationDto('250269812345678', null, null, 'icad', null, null),
            new LifeCycleDto('alive', null, null),
            new TransferDto('none', null, null),
            null,
            'active',
            [],
            'c',
            'u',
        );
        $bus = new AnsweringQueryBus([GetAnimalById::class => $animal('2020-05-01')]);

        $identity = new QueryBusPetDirectory($bus)->identityOf('clinic-1', 'a1');

        self::assertNotNull($identity);
        self::assertSame(['Moka', 'cat', null, true, 'female', '2020-05-01', '250269812345678'], [
            $identity->name, $identity->species, $identity->breedName, $identity->isMixedBreed, $identity->sex, $identity->birthDate?->format('Y-m-d'), $identity->microchipNumber,
        ]);
        $query = $bus->asked[0];
        self::assertInstanceOf(GetAnimalById::class, $query);
        self::assertSame(['clinic-1', 'a1'], [$query->clinicId, $query->animalId]);

        self::assertNull(new QueryBusPetDirectory(new AnsweringQueryBus([GetAnimalById::class => $animal(null)]))->identityOf('clinic-1', 'a1')?->birthDate);
        self::assertNull(new QueryBusPetDirectory(new AnsweringQueryBus())->identityOf('clinic-1', 'a1'));

        $notFound = new AnsweringQueryBus([GetAnimalById::class => static fn (): never => throw new \DomainException('Animal not found.')]);
        self::assertNull(new QueryBusPetDirectory($notFound)->identityOf('clinic-1', 'a1'));
    }

    public function testTheLatestPreventiveActsAreAnimals(): void
    {
        $view = new LatestPreventiveCareView('a1', 'vaccination', 'Rage', new \DateTimeImmutable('2025-11-10'), 'LOT', new \DateTimeImmutable('2026-11-10'));
        $bus  = new AnsweringQueryBus([ListLatestPreventiveCare::class => [$view]]);

        $acts = new QueryBusPreventiveCare($bus)->latestActs('clinic-1', ['a1']);

        self::assertSame([['a1', 'vaccination', 'Rage', '2025-11-10', '2026-11-10']], array_map(
            static fn (PreventiveAct $a): array => [$a->animalId, $a->kind, $a->label, $a->administeredOn->format('Y-m-d'), $a->nextDueOn?->format('Y-m-d')],
            $acts,
        ));
        $query = $bus->asked[0];
        self::assertInstanceOf(ListLatestPreventiveCare::class, $query);
        self::assertSame(['clinic-1', ['a1']], [$query->clinicId, $query->animalIds]);

        self::assertSame([], new QueryBusPreventiveCare($bus)->latestActs('clinic-1', []));
        self::assertSame([], new QueryBusPreventiveCare(new AnsweringQueryBus())->latestActs('clinic-1', ['a1']));
    }

    public function testTheLatestWeightRecordIsTheFirstOfAnimals(): void
    {
        $bus = new AnsweringQueryBus([ListAnimalWeightRecords::class => [
            new WeightRecordView('w2', 12_400, null, new \DateTimeImmutable('2026-03-10'), 'Dr A'),
            new WeightRecordView('w1', 12_000, 3, new \DateTimeImmutable('2026-03-01'), 'Dr A'),
        ]]);

        $latest = new QueryBusAnimalWeights($bus)->latestOf('clinic-1', 'a1');

        self::assertSame([12_400, '2026-03-10'], [$latest?->weightGrams, $latest?->measuredOn->format('Y-m-d')]);
        $query = $bus->asked[0];
        self::assertInstanceOf(ListAnimalWeightRecords::class, $query);
        self::assertSame(['clinic-1', 'a1'], [$query->clinicId, $query->animalId]);

        self::assertNull(new QueryBusAnimalWeights(new AnsweringQueryBus([ListAnimalWeightRecords::class => []]))->latestOf('clinic-1', 'a1'));
        self::assertNull(new QueryBusAnimalWeights(new AnsweringQueryBus())->latestOf('clinic-1', 'a1'));
    }

    public function testTheVaccinationsAreTheVaccinationKindOfThePreventiveCare(): void
    {
        $bus = new AnsweringQueryBus([ListAnimalPreventiveCare::class => [
            new PreventiveCareRecordView('p2', 'internal_parasite', 'Milbemax', new \DateTimeImmutable('2026-09-01'), null, null, 'Dr A'),
            new PreventiveCareRecordView('p1', 'vaccination', 'Rage', new \DateTimeImmutable('2025-11-10'), 'LOT', new \DateTimeImmutable('2026-11-10'), 'Dr A'),
        ]]);

        $records = new QueryBusVaccinationRecords($bus)->vaccinationsOf('clinic-1', 'a1');

        self::assertSame([['p1', 'Rage', '2025-11-10', 'LOT', '2026-11-10']], array_map(
            static fn (VaccinationRecord $r): array => [$r->id, $r->label, $r->administeredOn->format('Y-m-d'), $r->batchNumber, $r->nextDueOn?->format('Y-m-d')],
            $records,
        ));
        $query = $bus->asked[0];
        self::assertInstanceOf(ListAnimalPreventiveCare::class, $query);
        self::assertSame(['clinic-1', 'a1'], [$query->clinicId, $query->animalId]);

        self::assertSame([], new QueryBusVaccinationRecords(new AnsweringQueryBus())->vaccinationsOf('clinic-1', 'a1'));
    }

    public function testTheVisitSummariesAreConsultationsByConsultationId(): void
    {
        $at  = new \DateTimeImmutable('2026-10-20 08:30:00', new \DateTimeZone('UTC'));
        $bus = new AnsweringQueryBus([GetConsultationPortalSummaries::class => [new ConsultationPortalSummary('c1', $at, 'Vaccination')]]);

        $summaries = new QueryBusVisitSummaries($bus)->summariesOf('clinic-1', ['c1', 'c2']);

        self::assertSame(['c1'], array_keys($summaries));
        self::assertSame(['c1', $at, 'Vaccination'], [$summaries['c1']->consultationId, $summaries['c1']->startedAtUtc, $summaries['c1']->title]);
        $query = $bus->asked[0];
        self::assertInstanceOf(GetConsultationPortalSummaries::class, $query);
        self::assertSame(['clinic-1', ['c1', 'c2']], [$query->clinicId, $query->consultationIds]);

        self::assertSame([], new QueryBusVisitSummaries($bus)->summariesOf('clinic-1', []));
        self::assertCount(1, $bus->asked);
        self::assertSame([], new QueryBusVisitSummaries(new AnsweringQueryBus())->summariesOf('clinic-1', ['c1']));
    }

    public function testTheLatestClosedConsultationWeightIsConsultations(): void
    {
        $at  = new \DateTimeImmutable('2026-03-10 09:00:00', new \DateTimeZone('UTC'));
        $bus = new AnsweringQueryBus([GetLatestClosedConsultationWeight::class => new ConsultationWeight(12_400, $at)]);

        $weight = new QueryBusConsultationWeights($bus)->latestClosedOf('clinic-1', 'a1');

        self::assertSame([12_400, $at], [$weight?->weightGrams, $weight?->measuredAtUtc]);
        $query = $bus->asked[0];
        self::assertInstanceOf(GetLatestClosedConsultationWeight::class, $query);
        self::assertSame(['clinic-1', 'a1'], [$query->clinicId, $query->animalId]);

        self::assertNull(new QueryBusConsultationWeights(new AnsweringQueryBus())->latestClosedOf('clinic-1', 'a1'));
    }

    public function testTheNextAppointmentIsSchedulings(): void
    {
        $at  = new \DateTimeImmutable('2026-11-04 09:30:00', new \DateTimeZone('UTC'));
        $now = new \DateTimeImmutable('2026-10-29 09:00:00', new \DateTimeZone('UTC'));
        $bus = new AnsweringQueryBus([GetNextAppointmentForAnimals::class => new NextAppointmentItem('ap1', 'a1', $at, 'u1', 'vaccin')]);

        $next = new QueryBusOwnerAppointments($bus)->nextFor('clinic-1', ['a1', 'a2'], $now);

        self::assertNotNull($next);
        self::assertSame(['ap1', 'a1', $at, 'u1', 'vaccin'], [$next->id, $next->animalId, $next->startsAtUtc, $next->practitionerUserId, $next->category]);
        $query = $bus->asked[0];
        self::assertInstanceOf(GetNextAppointmentForAnimals::class, $query);
        self::assertSame(['clinic-1', ['a1', 'a2'], $now], [$query->clinicId, $query->animalIds, $query->nowUtc]);

        self::assertNull(new QueryBusOwnerAppointments($bus)->nextFor('clinic-1', [], $now));
        self::assertCount(1, $bus->asked, 'no animal, no query');
        self::assertNull(new QueryBusOwnerAppointments(new AnsweringQueryBus())->nextFor('clinic-1', ['a1'], $now));
    }

    public function testThePractitionerNamesAreThePrintedNames(): void
    {
        $printed = ['u1' => ' Dr Claire Lambert ', 'u2' => '  ', 'u3' => null];
        $bus     = new AnsweringQueryBus([GetStaffPrintedName::class => static fn (GetStaffPrintedName $q): ?string => $printed[$q->userId] ?? null]);

        self::assertSame(['u1' => 'Dr Claire Lambert'], new QueryBusPractitionerNames($bus)->namesOf('clinic-1', ['u1', 'u2', 'u3', 'u1', 'u9']));
        self::assertCount(4, $bus->asked, 'once per user');
        $query = $bus->asked[0];
        self::assertInstanceOf(GetStaffPrintedName::class, $query);
        self::assertSame(['clinic-1', 'u1'], [$query->clinicId, $query->userId]);

        self::assertSame([], new QueryBusPractitionerNames($bus)->namesOf('clinic-1', []));
        self::assertCount(4, $bus->asked, 'no user, no query');
    }

    public function testTheDocumentHeaderIsTheClinicsDocumentIdentity(): void
    {
        $clinic = new ClinicDto('clinic-1', 'Clinique des Lilas', 'lilas', 'Europe/Paris', 'fr-FR', 'FR', null, 'EUR', ClinicStatus::ACTIVE, null, 'c', 'u', null, '#4338ca', 'clinic/logo.png');

        $header = new QueryBusClinicProfile(new AnsweringQueryBus([GetClinic::class => $clinic]))->documentHeaderOf('clinic-1');

        self::assertSame(['Clinique des Lilas', null, '#4338ca', 'clinic/logo.png'], [$header?->name, $header?->letterhead, $header?->brandColor, $header?->logoKey]);
        self::assertNull(new QueryBusClinicProfile(new AnsweringQueryBus())->documentHeaderOf('clinic-1'));
    }
}
