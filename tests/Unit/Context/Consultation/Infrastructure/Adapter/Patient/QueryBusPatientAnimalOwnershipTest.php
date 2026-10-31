<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Consultation\Infrastructure\Adapter\Patient;

use App\Context\Animal\Application\Query\GetAnimalById\AnimalView;
use App\Context\Animal\Application\Query\GetAnimalById\GetAnimalById;
use App\Context\Animal\Application\Query\GetAnimalById\IdentificationDto;
use App\Context\Animal\Application\Query\GetAnimalById\LifeCycleDto;
use App\Context\Animal\Application\Query\GetAnimalById\OwnershipDto;
use App\Context\Animal\Application\Query\GetAnimalById\TransferDto;
use App\Context\Consultation\Application\Port\PatientAnimalOwnership;
use App\Context\Consultation\Infrastructure\Adapter\Patient\QueryBusPatientAnimalOwnership;
use App\Context\Patient\Application\Query\GetPatientAnimalLink\GetPatientAnimalLink;
use App\Context\Patient\Application\Query\GetPatientAnimalLink\PatientAnimalLinkDto;
use App\Tests\Support\AnsweringQueryBus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(QueryBusPatientAnimalOwnership::class)]
final class QueryBusPatientAnimalOwnershipTest extends TestCase
{
    private const string CLINIC_ID  = '01960000-0000-7000-8000-000000000001';
    private const string PATIENT_ID = '01960000-0000-7000-8000-0000000c0004';
    private const string ANIMAL_ID  = '01960000-0000-7000-8000-0000000c0005';
    private const string CLIENT_ID  = '01960000-0000-7000-8000-0000000c0006';

    public function testTheAnimalAndItsActivePrimaryOwner(): void
    {
        $ownership = $this->ownershipOf(self::linked(), self::animal([new OwnershipDto(self::CLIENT_ID, 'primary', 'active', '2024-04-10', null)]));

        self::assertSame([self::ANIMAL_ID, self::CLIENT_ID], [$ownership->animalId, $ownership->primaryOwnerClientId]);
    }

    public function testAnEndedOrSecondaryOwnershipIsNoPrimaryOwner(): void
    {
        $ownership = $this->ownershipOf(self::linked(), self::animal([
            new OwnershipDto(self::CLIENT_ID, 'primary', 'ended', '2024-04-10', '2025-01-01'),
            new OwnershipDto(self::CLIENT_ID, 'secondary', 'active', '2024-04-10', null),
        ]));

        self::assertSame([self::ANIMAL_ID, null], [$ownership->animalId, $ownership->primaryOwnerClientId]);
    }

    public function testAnUnreadableAnimalHasNoOwner(): void
    {
        $thrown = $this->ownershipOf(self::linked(), static fn (): never => throw new \DomainException('Unknown animal'));
        self::assertSame([self::ANIMAL_ID, null], [$thrown->animalId, $thrown->primaryOwnerClientId]);

        $missing = $this->ownershipOf(self::linked(), null);
        self::assertSame([self::ANIMAL_ID, null], [$missing->animalId, $missing->primaryOwnerClientId]);
    }

    public function testAPatientNotLinkedToAnAnimalHasNeither(): void
    {
        $unreconciled = $this->ownershipOf(new PatientAnimalLinkDto(self::PATIENT_ID, null, 'Chat tigré', 'Chat', null), null);
        self::assertSame([null, null], [$unreconciled->animalId, $unreconciled->primaryOwnerClientId]);

        $unknown = $this->ownershipOf(null, null);
        self::assertSame([null, null], [$unknown->animalId, $unknown->primaryOwnerClientId]);
    }

    private function ownershipOf(mixed $link, mixed $animal): PatientAnimalOwnership
    {
        $bus = new AnsweringQueryBus([GetPatientAnimalLink::class => $link, GetAnimalById::class => $animal]);

        return new QueryBusPatientAnimalOwnership($bus)->ownershipOf(self::CLINIC_ID, self::PATIENT_ID);
    }

    private static function linked(): PatientAnimalLinkDto
    {
        return new PatientAnimalLinkDto(self::PATIENT_ID, self::ANIMAL_ID, 'Milo', null, null);
    }

    /**
     * @param list<OwnershipDto> $ownerships
     */
    private static function animal(array $ownerships): AnimalView
    {
        return new AnimalView(
            id: self::ANIMAL_ID,
            clinicId: self::CLINIC_ID,
            name: 'Milo',
            species: 'dog',
            sex: 'female',
            reproductiveStatus: 'neutered',
            isMixedBreed: false,
            breedName: null,
            birthDate: null,
            color: null,
            photoUrl: null,
            identification: new IdentificationDto(null, null, null, 'ICAD', null, null),
            lifeCycle: new LifeCycleDto('alive', null, null),
            transfer: new TransferDto('none', null, null),
            auxiliaryContact: null,
            status: 'active',
            ownerships: $ownerships,
            createdAt: '2024-04-10',
            updatedAt: '2024-04-10',
        );
    }
}
