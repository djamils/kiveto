<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Consultation\Infrastructure\Adapter\Patient;

use App\Context\Animal\Application\Query\GetAnimalById\AnimalView;
use App\Context\Animal\Application\Query\GetAnimalById\GetAnimalById;
use App\Context\Animal\Application\Query\GetAnimalById\IdentificationDto;
use App\Context\Animal\Application\Query\GetAnimalById\LifeCycleDto;
use App\Context\Animal\Application\Query\GetAnimalById\OwnershipDto;
use App\Context\Animal\Application\Query\GetAnimalById\TransferDto;
use App\Context\Client\Application\Query\GetClientById\ClientView;
use App\Context\Client\Application\Query\GetClientById\ContactMethodDto;
use App\Context\Client\Application\Query\GetClientById\GetClientById;
use App\Context\Client\Application\Query\GetClientById\PostalAddressDto;
use App\Context\Consultation\Infrastructure\Adapter\Animal\QueryBusAnimalReportReader;
use App\Context\Consultation\Infrastructure\Adapter\Client\QueryBusOwnerReportReader;
use App\Context\Consultation\Infrastructure\Adapter\Patient\QueryBusPatientSnapshotProvider;
use App\Context\Patient\Application\Query\GetPatientAnimalLink\GetPatientAnimalLink;
use App\Context\Patient\Application\Query\GetPatientAnimalLink\PatientAnimalLinkDto;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Support\AnsweringQueryBus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(QueryBusPatientSnapshotProvider::class)]
#[CoversClass(QueryBusAnimalReportReader::class)]
#[CoversClass(QueryBusOwnerReportReader::class)]
final class QueryBusPatientSnapshotProviderTest extends TestCase
{
    private const string CLINIC_ID  = '01960000-0000-7000-8000-000000000001';
    private const string PATIENT_ID = '01960000-0000-7000-8000-0000000c0004';
    private const string ANIMAL_ID  = '01960000-0000-7000-8000-0000000c0005';
    private const string CLIENT_ID  = '01960000-0000-7000-8000-0000000c0006';

    public function testAnIdentifiedAnimalAndItsPrimaryOwnerAreWordedForTheReport(): void
    {
        $bus = $this->bus(self::animal(), self::client([
            new ContactMethodDto('phone', 'Domicile', '+33142641800', false),
            new ContactMethodDto('phone', 'Mobile', '+33612345678', true),
            new ContactMethodDto('email', 'Perso', 'sophie@example.fr', false),
        ]));

        $snapshot = $this->provider($bus)->snapshot(self::CLINIC_ID, self::PATIENT_ID);

        self::assertSame('Milo', $snapshot->patient->name);
        self::assertSame('Chien', $snapshot->patient->speciesLabel);
        self::assertSame('Croisé', $snapshot->patient->breed);
        self::assertSame('♀', $snapshot->patient->sexLabel);
        self::assertTrue($snapshot->patient->sterilized);
        self::assertSame('2024-04-10', $snapshot->patient->birthDate);
        self::assertSame('2 ans', $snapshot->patient->ageLabel);
        self::assertSame('250269812345678', $snapshot->patient->microchip);

        self::assertSame(self::CLIENT_ID, $snapshot->owner?->clientId);
        self::assertSame('Sophie Garnier', $snapshot->owner->fullName);
        self::assertSame('+33612345678', $snapshot->owner->phone);
        self::assertSame('sophie@example.fr', $snapshot->owner->email);
        self::assertSame('18 rue des Abbesses 75018 Paris', $snapshot->owner->address);
    }

    public function testAnOwnerWithoutContactOrAddressPrintsNone(): void
    {
        $snapshot = $this->provider($this->bus(self::animal(), self::client([], null)))->snapshot(self::CLINIC_ID, self::PATIENT_ID);

        self::assertNull($snapshot->owner?->phone);
        self::assertNull($snapshot->owner?->email);
        self::assertNull($snapshot->owner?->address);
    }

    public function testAnAnimalWithoutPrimaryOwnerHasNoOwnerBlock(): void
    {
        $snapshot = $this->provider($this->bus(self::animal(ownerships: []), self::client([])))->snapshot(self::CLINIC_ID, self::PATIENT_ID);

        self::assertSame('Milo', $snapshot->patient->name);
        self::assertNull($snapshot->owner);
    }

    public function testAnOwnerThatCannotBeReadHasNoOwnerBlock(): void
    {
        $bus = $this->bus(self::animal(), static fn (): never => throw new \DomainException('Unknown client'));

        self::assertNull($this->provider($bus)->snapshot(self::CLINIC_ID, self::PATIENT_ID)->owner);
        self::assertNull($this->provider($this->bus(self::animal(), null))->snapshot(self::CLINIC_ID, self::PATIENT_ID)->owner);
    }

    public function testAPatientNotReconciledYetPrintsItsProvisionalLabel(): void
    {
        $bus = new AnsweringQueryBus([GetPatientAnimalLink::class => new PatientAnimalLinkDto(self::PATIENT_ID, null, 'Chat tigré', 'Chat', 'tigré')]);

        $snapshot = $this->provider($bus)->snapshot(self::CLINIC_ID, self::PATIENT_ID);

        self::assertSame('Chat tigré', $snapshot->patient->name);
        self::assertSame('Chat', $snapshot->patient->speciesLabel);
        self::assertNull($snapshot->patient->breed);
        self::assertNull($snapshot->owner);
    }

    public function testAnUnknownPatientOrAnUnreadableAnimalPrintsAsUnidentified(): void
    {
        $unknown = $this->provider(new AnsweringQueryBus())->snapshot(self::CLINIC_ID, self::PATIENT_ID);
        self::assertSame('Patient non identifié', $unknown->patient->name);
        self::assertNull($unknown->patient->speciesLabel);

        $unreadable = $this->bus(static fn (): never => throw new \DomainException('Unknown animal'), null);
        self::assertSame('Fifi', $this->provider($unreadable)->snapshot(self::CLINIC_ID, self::PATIENT_ID)->patient->name);

        self::assertSame('Fifi', $this->provider($this->bus(null, null))->snapshot(self::CLINIC_ID, self::PATIENT_ID)->patient->name);
    }

    /**
     * @return iterable<string, array{string, string, string|null, string|null}>
     */
    public static function provideSpeciesSexAndAgeAreWordedForPaperCases(): iterable
    {
        yield 'cat, male, months' => ['cat', 'male', '2026-03-01', '7 mois'];
        yield 'nac, unknown sex' => ['nac', 'unknown', '2025-10-24', '1 an'];
        yield 'bird, days' => ['bird', 'female', '2026-10-24', '2 jours'];
        yield 'one day' => ['dog', 'female', '2026-10-25', '1 jour'];
        yield 'no birth date' => ['dog', 'female', null, null];
        yield 'unreadable birthday' => ['dog', 'female', 'not a date', null];
    }

    #[DataProvider('provideSpeciesSexAndAgeAreWordedForPaperCases')]
    public function testSpeciesSexAndAgeAreWordedForPaper(string $species, string $sex, ?string $birthDate, ?string $ageLabel): void
    {
        $patient = $this->provider($this->bus(self::animal($species, $sex, $birthDate), self::client([])))
            ->snapshot(self::CLINIC_ID, self::PATIENT_ID)->patient
        ;

        self::assertSame(['cat' => 'Chat', 'nac' => 'NAC', 'bird' => 'Autre', 'dog' => 'Chien'][$species], $patient->speciesLabel);
        self::assertSame(['male' => '♂', 'female' => '♀', 'unknown' => null][$sex], $patient->sexLabel);
        self::assertSame($ageLabel, $patient->ageLabel);
    }

    private function provider(AnsweringQueryBus $bus): QueryBusPatientSnapshotProvider
    {
        return new QueryBusPatientSnapshotProvider(
            $bus,
            new QueryBusAnimalReportReader($bus, new FrozenClock(new \DateTimeImmutable('2026-10-26 10:00:00'))),
            new QueryBusOwnerReportReader($bus),
        );
    }

    private function bus(mixed $animal, mixed $client): AnsweringQueryBus
    {
        return new AnsweringQueryBus([
            GetPatientAnimalLink::class => new PatientAnimalLinkDto(self::PATIENT_ID, self::ANIMAL_ID, 'Fifi', null, null),
            GetAnimalById::class        => $animal,
            GetClientById::class        => $client,
        ]);
    }

    /**
     * @param list<OwnershipDto>|null $ownerships
     */
    private static function animal(string $species = 'dog', string $sex = 'female', ?string $birthDate = '2024-04-10', ?array $ownerships = null): AnimalView
    {
        return new AnimalView(
            id: self::ANIMAL_ID,
            clinicId: self::CLINIC_ID,
            name: 'Milo',
            species: $species,
            sex: $sex,
            reproductiveStatus: 'neutered',
            isMixedBreed: true,
            breedName: 'Labrador',
            birthDate: $birthDate,
            color: null,
            photoUrl: null,
            identification: new IdentificationDto('250269812345678', null, null, 'ICAD', null, null),
            lifeCycle: new LifeCycleDto('alive', null, null),
            transfer: new TransferDto('none', null, null),
            auxiliaryContact: null,
            status: 'active',
            ownerships: $ownerships ?? [new OwnershipDto(self::CLIENT_ID, 'primary', 'active', '2024-04-10', null)],
            createdAt: '2024-04-10',
            updatedAt: '2024-04-10',
        );
    }

    /**
     * @param list<ContactMethodDto> $contacts
     */
    private static function client(array $contacts, ?PostalAddressDto $address = new PostalAddressDto('18 rue des Abbesses', 'Paris', 'FR', postalCode: '75018')): ClientView
    {
        return new ClientView(
            id: self::CLIENT_ID,
            clinicId: self::CLINIC_ID,
            firstName: 'Sophie',
            lastName: 'Garnier',
            status: 'active',
            contactMethods: $contacts,
            postalAddress: $address,
            createdAt: '2024-04-10',
            updatedAt: '2024-04-10',
        );
    }
}
