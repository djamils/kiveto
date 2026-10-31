<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Animal\Infrastructure\Persistence\Doctrine;

use App\Context\Animal\Application\Port\AnimalReadRepositoryInterface;
use App\Context\Animal\Application\Query\ListLatestPreventiveCare\LatestPreventiveCareView;
use App\Context\Animal\Domain\ValueObject\AnimalStatus;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use App\Context\Animal\Domain\ValueObject\LifeStatus;
use App\Context\Animal\Domain\ValueObject\OwnershipRole;
use App\Context\Animal\Domain\ValueObject\OwnershipStatus;
use App\Context\Animal\Domain\ValueObject\PreventiveCareKind;
use App\Context\Animal\Domain\ValueObject\ReproductiveStatus;
use App\Context\Animal\Domain\ValueObject\Sex;
use App\Context\Animal\Domain\ValueObject\Species;
use App\Context\Animal\Domain\ValueObject\TransferStatus;
use App\Fixtures\Context\Animal\Factory\AnimalEntityFactory;
use App\Fixtures\Context\Animal\Factory\OwnershipEntityFactory;
use App\Fixtures\Context\Animal\Factory\PreventiveCareRecordEntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

final class DoctrineAnimalReadRepositoryPortalTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC_ID    = '12345678-9abc-def0-1234-56789abcdef0';
    private const string OTHER_CLINIC = '88888888-8888-7888-8888-888888888888';
    private const string CLIENT_A     = '0192d000-0000-7000-8000-00000000000a';
    private const string CLIENT_B     = '0192d000-0000-7000-8000-00000000000b';
    private const string STRANGER     = '0192d000-0000-7000-8000-0000000000ff';
    private const string ANIMAL_ID    = '0192b000-0000-7000-8000-000000000001';
    private const string OTHER_ANIMAL = '0192b000-0000-7000-8000-000000000002';

    public function testListsOnlyActiveUntransferredPetsOfAnActivePrimaryOwner(): void
    {
        $rex = $this->pet('0192e000-0000-7000-8000-000000000001', 'Rex', [
            'species'            => Species::DOG,
            'sex'                => Sex::MALE,
            'reproductiveStatus' => ReproductiveStatus::NEUTERED,
            'breedName'          => 'Labrador',
            'isMixedBreed'       => false,
            'birthDate'          => new \DateTimeImmutable('2019-04-12 00:00:00'),
            'microchipNumber'    => '250269812345678',
        ]);
        $this->own($rex, self::CLIENT_A, OwnershipRole::PRIMARY);
        // A secondary owner never makes a pet visible.
        $this->own($rex, self::STRANGER, OwnershipRole::SECONDARY);

        $luna = $this->pet('0192e000-0000-7000-8000-000000000002', 'Luna', [
            'species'            => Species::CAT,
            'sex'                => Sex::FEMALE,
            'reproductiveStatus' => ReproductiveStatus::INTACT,
            'breedName'          => null,
            'isMixedBreed'       => true,
            'birthDate'          => null,
            'microchipNumber'    => null,
        ]);
        $this->own($luna, self::CLIENT_B, OwnershipRole::PRIMARY);

        $secondary = $this->pet('0192e000-0000-7000-8000-000000000003', 'Secondaire', []);
        $this->own($secondary, self::STRANGER, OwnershipRole::PRIMARY);
        $this->own($secondary, self::CLIENT_A, OwnershipRole::SECONDARY);

        $archived = $this->pet('0192e000-0000-7000-8000-000000000004', 'Archivé', ['status' => AnimalStatus::ARCHIVED]);
        $this->own($archived, self::CLIENT_A, OwnershipRole::PRIMARY);

        $sold = $this->pet('0192e000-0000-7000-8000-000000000005', 'Vendu', ['transferStatus' => TransferStatus::SOLD]);
        $this->own($sold, self::CLIENT_A, OwnershipRole::PRIMARY);

        $given = $this->pet('0192e000-0000-7000-8000-000000000006', 'Donné', ['transferStatus' => TransferStatus::GIVEN]);
        $this->own($given, self::CLIENT_A, OwnershipRole::PRIMARY);

        $formerOwner = $this->pet('0192e000-0000-7000-8000-000000000007', 'Ancien', []);
        $this->own($formerOwner, self::CLIENT_A, OwnershipRole::PRIMARY, OwnershipStatus::ENDED);

        $otherClinic = $this->pet('0192e000-0000-7000-8000-000000000008', 'Ailleurs', [], self::OTHER_CLINIC);
        $this->own($otherClinic, self::CLIENT_A, OwnershipRole::PRIMARY);

        $this->flush();

        $pets = $this->repository()->listPortalPets(
            ClinicId::fromString(self::CLINIC_ID),
            [self::CLIENT_A, self::CLIENT_B],
        );

        self::assertSame(['Luna', 'Rex'], array_map(static fn ($pet): string => $pet->name, $pets));

        [$lunaView, $rexView] = $pets;
        self::assertSame('0192e000-0000-7000-8000-000000000001', $rexView->id);
        self::assertSame(self::CLIENT_A, $rexView->primaryOwnerClientId);
        self::assertSame('dog', $rexView->species);
        self::assertSame('Labrador', $rexView->breedName);
        self::assertFalse($rexView->isMixedBreed);
        self::assertSame('male', $rexView->sex);
        self::assertSame('neutered', $rexView->reproductiveStatus);
        self::assertSame('2019-04-12', $rexView->birthDate?->format('Y-m-d'));
        self::assertSame('alive', $rexView->lifeStatus);
        self::assertSame('250269812345678', $rexView->microchipNumber);

        self::assertSame(self::CLIENT_B, $lunaView->primaryOwnerClientId);
        self::assertSame('cat', $lunaView->species);
        self::assertNull($lunaView->breedName);
        self::assertTrue($lunaView->isMixedBreed);
        self::assertSame('female', $lunaView->sex);
        self::assertSame('intact', $lunaView->reproductiveStatus);
        self::assertNull($lunaView->birthDate);
        self::assertNull($lunaView->microchipNumber);
    }

    public function testKeepsDeceasedAndMissingPetsAfterTheAliveOnes(): void
    {
        $this->own($this->pet('0192e000-0000-7000-8000-000000000011', 'Anubis', ['lifeStatus' => LifeStatus::DECEASED]), self::CLIENT_A, OwnershipRole::PRIMARY);
        $this->own($this->pet('0192e000-0000-7000-8000-000000000012', 'Zorro', []), self::CLIENT_A, OwnershipRole::PRIMARY);
        $this->own($this->pet('0192e000-0000-7000-8000-000000000013', 'Bijou', ['lifeStatus' => LifeStatus::MISSING]), self::CLIENT_A, OwnershipRole::PRIMARY);
        $this->own($this->pet('0192e000-0000-7000-8000-000000000014', 'Milo', []), self::CLIENT_A, OwnershipRole::PRIMARY);
        $this->flush();

        $pets = $this->repository()->listPortalPets(ClinicId::fromString(self::CLINIC_ID), [self::CLIENT_A]);

        self::assertSame(
            [['Milo', 'alive'], ['Zorro', 'alive'], ['Anubis', 'deceased'], ['Bijou', 'missing']],
            array_map(static fn ($pet): array => [$pet->name, $pet->lifeStatus], $pets),
        );
    }

    public function testReturnsNoPetForNoClient(): void
    {
        self::assertSame([], $this->repository()->listPortalPets(ClinicId::fromString(self::CLINIC_ID), []));
    }

    public function testTheLatestActWithoutDueDateHidesAnOlderOneThatHasIt(): void
    {
        $this->act(self::ANIMAL_ID, PreventiveCareKind::VACCINATION, 'Rage', '2025-03-01', '2026-03-01', 'RB-1');
        $this->act(self::ANIMAL_ID, PreventiveCareKind::VACCINATION, 'Rage', '2026-02-20', null, 'RB-2');
        $this->flush();

        $acts = $this->latest([self::ANIMAL_ID]);

        self::assertCount(1, $acts);
        self::assertSame(self::ANIMAL_ID, $acts[0]->animalId);
        self::assertSame('vaccination', $acts[0]->kind);
        self::assertSame('Rage', $acts[0]->label);
        self::assertSame('2026-02-20', $acts[0]->administeredOn->format('Y-m-d'));
        self::assertSame('RB-2', $acts[0]->batchNumber);
        self::assertNull($acts[0]->nextDueOn);
    }

    public function testTheSameLabelWithAnotherCaseIsTheSameProtocol(): void
    {
        $this->act(self::ANIMAL_ID, PreventiveCareKind::VACCINATION, 'CHPPiL', '2025-01-10', '2026-01-10', null);
        $this->act(self::ANIMAL_ID, PreventiveCareKind::VACCINATION, 'chppil', '2026-01-12', '2027-01-12', null);
        // Same day: the act recorded last wins.
        $this->act(self::ANIMAL_ID, PreventiveCareKind::INTERNAL_PARASITE, 'Milbemax', '2026-05-01', '2026-08-01', null, '2026-05-01 09:00:00');
        $this->act(self::ANIMAL_ID, PreventiveCareKind::INTERNAL_PARASITE, 'MILBEMAX', '2026-05-01', '2026-09-01', null, '2026-05-01 17:00:00');
        $this->flush();

        $acts = $this->latest([self::ANIMAL_ID]);

        self::assertCount(2, $acts);
        $byKind = [];
        foreach ($acts as $act) {
            $byKind[$act->kind] = $act;
        }
        self::assertSame('chppil', $byKind['vaccination']->label);
        self::assertSame('2027-01-12', $byKind['vaccination']->nextDueOn?->format('Y-m-d'));
        self::assertSame('MILBEMAX', $byKind['internal_parasite']->label);
        self::assertSame('2026-09-01', $byKind['internal_parasite']->nextDueOn?->format('Y-m-d'));
    }

    public function testTwoKindsWithTheSameLabelAreTwoProtocols(): void
    {
        $this->act(self::ANIMAL_ID, PreventiveCareKind::INTERNAL_PARASITE, 'Bravecto', '2026-04-01', '2026-07-01', null);
        $this->act(self::ANIMAL_ID, PreventiveCareKind::EXTERNAL_PARASITE, 'Bravecto', '2026-04-02', '2026-07-02', null);
        $this->act(self::OTHER_ANIMAL, PreventiveCareKind::EXTERNAL_PARASITE, 'Bravecto', '2026-04-03', '2026-07-03', null);
        // Neither another clinic nor an animal outside the list leaks in.
        $this->act(self::ANIMAL_ID, PreventiveCareKind::VACCINATION, 'Rage', '2026-04-04', null, null, null, self::OTHER_CLINIC);
        $this->act('0192b000-0000-7000-8000-000000000099', PreventiveCareKind::VACCINATION, 'Rage', '2026-04-05', null, null);
        $this->flush();

        $acts = $this->latest([self::ANIMAL_ID, self::OTHER_ANIMAL]);

        self::assertSame(
            [
                [self::ANIMAL_ID, 'external_parasite', '2026-07-02'],
                [self::ANIMAL_ID, 'internal_parasite', '2026-07-01'],
                [self::OTHER_ANIMAL, 'external_parasite', '2026-07-03'],
            ],
            array_map(
                static fn (LatestPreventiveCareView $act): array => [$act->animalId, $act->kind, $act->nextDueOn?->format('Y-m-d')],
                $acts,
            ),
        );
    }

    public function testReturnsNoActForNoAnimal(): void
    {
        self::assertSame([], $this->latest([]));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function pet(string $id, string $name, array $attributes, string $clinicId = self::CLINIC_ID): object
    {
        return AnimalEntityFactory::createOne(array_merge([
            'id'             => Uuid::fromString($id),
            'clinicId'       => Uuid::fromString($clinicId),
            'name'           => $name,
            'status'         => AnimalStatus::ACTIVE,
            'lifeStatus'     => LifeStatus::ALIVE,
            'transferStatus' => TransferStatus::NONE,
        ], $attributes));
    }

    private function own(
        object $animal,
        string $clientId,
        OwnershipRole $role,
        OwnershipStatus $status = OwnershipStatus::ACTIVE,
    ): void {
        OwnershipEntityFactory::createOne([
            'animal'   => $animal,
            'clientId' => Uuid::fromString($clientId),
            'role'     => $role,
            'status'   => $status,
        ]);
    }

    private function act(
        string $animalId,
        PreventiveCareKind $kind,
        string $label,
        string $on,
        ?string $nextDueOn,
        ?string $batch,
        ?string $recordedAt = null,
        string $clinicId = self::CLINIC_ID,
    ): void {
        PreventiveCareRecordEntityFactory::createOne([
            'clinicId'       => Uuid::fromString($clinicId),
            'animalId'       => Uuid::fromString($animalId),
            'kind'           => $kind,
            'label'          => $label,
            'administeredOn' => new \DateTimeImmutable($on),
            'batchNumber'    => $batch,
            'nextDueOn'      => null === $nextDueOn ? null : new \DateTimeImmutable($nextDueOn),
            'recordedAt'     => new \DateTimeImmutable($recordedAt ?? $on . ' 18:00:00'),
        ]);
    }

    /**
     * @param list<string> $animalIds
     *
     * @return list<LatestPreventiveCareView>
     */
    private function latest(array $animalIds): array
    {
        return $this->repository()->listLatestPreventiveCare(ClinicId::fromString(self::CLINIC_ID), $animalIds);
    }

    private function repository(): AnimalReadRepositoryInterface
    {
        $repository = static::getContainer()->get(AnimalReadRepositoryInterface::class);
        \assert($repository instanceof AnimalReadRepositoryInterface);

        return $repository;
    }

    private function flush(): void
    {
        /* @phpstan-ignore-next-line method.nonObject, method.notFound */
        static::getContainer()->get('doctrine')->getManager()->flush();
    }
}
