<?php

declare(strict_types=1);

namespace App\Tests\Integration\Animal\Infrastructure\Persistence\Doctrine\Repository;

use App\Animal\Domain\Animal;
use App\Animal\Domain\Repository\AnimalRepositoryInterface;
use App\Animal\Domain\ValueObject\AnimalId;
use App\Animal\Domain\ValueObject\AnimalStatus;
use App\Animal\Domain\ValueObject\AuxiliaryContact;
use App\Animal\Domain\ValueObject\Identification;
use App\Animal\Domain\ValueObject\LifeCycle;
use App\Animal\Domain\ValueObject\OwnershipRole;
use App\Animal\Domain\ValueObject\OwnershipStatus;
use App\Animal\Domain\ValueObject\ReproductiveStatus;
use App\Animal\Domain\ValueObject\Sex;
use App\Animal\Domain\ValueObject\Species;
use App\Animal\Domain\ValueObject\Transfer;
use App\Clinic\Domain\ValueObject\ClinicId;
use App\Fixtures\Animal\Factory\AnimalEntityFactory;
use App\Fixtures\Animal\Factory\OwnershipEntityFactory;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

final class DoctrineAnimalRepositoryTest extends KernelTestCase
{
    use Factories;

    public function testSaveAndFindAnimal(): void
    {
        $clinicId = ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');

        /** @var AnimalRepositoryInterface $repo */
        $repo = static::getContainer()->get(AnimalRepositoryInterface::class);

        $animalId = AnimalId::fromString(Uuid::v7()->toString());

        $animal = Animal::create(
            id: $animalId,
            clinicId: $clinicId,
            name: 'Rex',
            species: Species::DOG,
            sex: Sex::MALE,
            reproductiveStatus: ReproductiveStatus::INTACT,
            isMixedBreed: false,
            breedName: 'Labrador',
            birthDate: new \DateTimeImmutable('2020-01-01'),
            color: 'Golden',
            photoUrl: null,
            identification: Identification::createEmpty(),
            lifeCycle: LifeCycle::alive(),
            transfer: Transfer::none(),
            auxiliaryContact: null,
            primaryOwnerClientId: Uuid::v7()->toString(),
            secondaryOwnerClientIds: [],
            now: new \DateTimeImmutable('2024-01-01T10:00:00+00:00')
        );

        $repo->save($animal);

        $foundAnimal = $repo->findById($clinicId, $animalId);

        self::assertNotNull($foundAnimal);
        self::assertSame('Rex', $foundAnimal->name());
        self::assertSame(Species::DOG, $foundAnimal->species());
        self::assertSame(AnimalStatus::ACTIVE, $foundAnimal->status());
    }

    public function testFindByIdReturnsNullWhenAnimalNotFound(): void
    {
        $clinicId = ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');
        $animalId = AnimalId::fromString('01234567-89ab-cdef-0123-456789abcdef');

        /** @var AnimalRepositoryInterface $repo */
        $repo = static::getContainer()->get(AnimalRepositoryInterface::class);

        $animal = $repo->findById($clinicId, $animalId);

        self::assertNull($animal);
    }

    public function testSaveUpdatesExistingAnimal(): void
    {
        $clinicId = ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');

        /** @var AnimalRepositoryInterface $repo */
        $repo = static::getContainer()->get(AnimalRepositoryInterface::class);

        $animalId = AnimalId::fromString(Uuid::v7()->toString());

        $animal = Animal::create(
            id: $animalId,
            clinicId: $clinicId,
            name: 'Rex',
            species: Species::DOG,
            sex: Sex::MALE,
            reproductiveStatus: ReproductiveStatus::INTACT,
            isMixedBreed: false,
            breedName: null,
            birthDate: null,
            color: null,
            photoUrl: null,
            identification: Identification::createEmpty(),
            lifeCycle: LifeCycle::alive(),
            transfer: Transfer::none(),
            auxiliaryContact: null,
            primaryOwnerClientId: Uuid::v7()->toString(),
            secondaryOwnerClientIds: [],
            now: new \DateTimeImmutable('2024-01-01T10:00:00+00:00')
        );

        $repo->save($animal);

        // Update the animal
        $foundAnimal = $repo->findById($clinicId, $animalId);
        \assert(null !== $foundAnimal);
        $foundAnimal->updateIdentity(
            name: 'Max',
            species: Species::DOG,
            sex: Sex::MALE,
            reproductiveStatus: ReproductiveStatus::INTACT,
            isMixedBreed: false,
            breedName: 'Golden Retriever',
            birthDate: new \DateTimeImmutable('2019-01-01'),
            color: 'Golden',
            photoUrl: null,
            identification: Identification::createEmpty(),
            auxiliaryContact: null,
            now: new \DateTimeImmutable('2024-06-01T10:00:00+00:00')
        );

        $repo->save($foundAnimal);

        // Clear the EntityManager to avoid identity map conflicts
        /** @var Registry $doctrine */
        $doctrine = static::getContainer()->get('doctrine');
        $doctrine->getManager()->clear();

        $updatedAnimal = $repo->findById($clinicId, $animalId);
        \assert(null !== $updatedAnimal);

        self::assertSame('Max', $updatedAnimal->name());
        self::assertSame('Golden Retriever', $updatedAnimal->breedName());
    }

    public function testSaveWithAuxiliaryContact(): void
    {
        $clinicId = ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');

        /** @var AnimalRepositoryInterface $repo */
        $repo = static::getContainer()->get(AnimalRepositoryInterface::class);

        $animalId = AnimalId::fromString(Uuid::v7()->toString());

        $auxiliaryContact = new AuxiliaryContact('John', 'Doe', '+33612345678');

        $animal = Animal::create(
            id: $animalId,
            clinicId: $clinicId,
            name: 'Rex',
            species: Species::DOG,
            sex: Sex::MALE,
            reproductiveStatus: ReproductiveStatus::INTACT,
            isMixedBreed: false,
            breedName: null,
            birthDate: null,
            color: null,
            photoUrl: null,
            identification: Identification::createEmpty(),
            lifeCycle: LifeCycle::alive(),
            transfer: Transfer::none(),
            auxiliaryContact: $auxiliaryContact,
            primaryOwnerClientId: Uuid::v7()->toString(),
            secondaryOwnerClientIds: [],
            now: new \DateTimeImmutable('2024-01-01T10:00:00+00:00')
        );

        $repo->save($animal);

        $foundAnimal = $repo->findById($clinicId, $animalId);
        \assert(null !== $foundAnimal);

        self::assertNotNull($foundAnimal->auxiliaryContact());
        self::assertSame('John', $foundAnimal->auxiliaryContact()->firstName);
    }

    public function testExistsMicrochip(): void
    {
        $clinicUuid = Uuid::v7();
        $clinicId   = ClinicId::fromString($clinicUuid->toString());

        $animal = AnimalEntityFactory::createOne([
            'clinicId'        => $clinicUuid,
            'microchipNumber' => '123456789',
        ]);

        /** @var AnimalRepositoryInterface $repo */
        $repo = static::getContainer()->get(AnimalRepositoryInterface::class);

        self::assertTrue($repo->existsByMicrochip($clinicId, '123456789'));
        self::assertFalse($repo->existsByMicrochip($clinicId, '987654321'));

        $animalId = AnimalId::fromString($animal->getId()->toString());
        self::assertFalse($repo->existsByMicrochip($clinicId, '123456789', $animalId));
    }

    public function testExistsMicrochipInDifferentClinic(): void
    {
        $clinic1Uuid = Uuid::v7();
        $clinic2Uuid = Uuid::v7();
        $clinic1Id   = ClinicId::fromString($clinic1Uuid->toString());
        $clinic2Id   = ClinicId::fromString($clinic2Uuid->toString());

        AnimalEntityFactory::createOne([
            'clinicId'        => $clinic1Uuid,
            'microchipNumber' => '123456789',
        ]);

        /** @var AnimalRepositoryInterface $repo */
        $repo = static::getContainer()->get(AnimalRepositoryInterface::class);

        self::assertTrue($repo->existsByMicrochip($clinic1Id, '123456789'));
        self::assertFalse($repo->existsByMicrochip($clinic2Id, '123456789'));
    }

    public function testFindByActiveOwner(): void
    {
        $clinicUuid = Uuid::v7();
        $clinicId   = ClinicId::fromString($clinicUuid->toString());
        $client123  = Uuid::v7();
        $client456  = Uuid::v7();

        $animal1 = AnimalEntityFactory::createOne([
            'clinicId' => $clinicUuid,
            'name'     => 'Rex',
        ]);

        $animal2 = AnimalEntityFactory::createOne([
            'clinicId' => $clinicUuid,
            'name'     => 'Max',
        ]);

        OwnershipEntityFactory::createOne([
            'animal'   => $animal1,
            'clientId' => $client123,
            'role'     => OwnershipRole::PRIMARY,
            'status'   => OwnershipStatus::ACTIVE,
        ]);

        OwnershipEntityFactory::createOne([
            'animal'   => $animal2,
            'clientId' => $client123,
            'role'     => OwnershipRole::PRIMARY,
            'status'   => OwnershipStatus::ACTIVE,
        ]);

        OwnershipEntityFactory::createOne([
            'animal'   => $animal2,
            'clientId' => $client456,
            'role'     => OwnershipRole::SECONDARY,
            'status'   => OwnershipStatus::ACTIVE,
        ]);

        /** @var AnimalRepositoryInterface $repo */
        $repo = static::getContainer()->get(AnimalRepositoryInterface::class);

        $animalsFor123 = $repo->findByActiveOwner($clinicId, $client123->toString());
        self::assertCount(2, $animalsFor123);

        $animalsFor456 = $repo->findByActiveOwner($clinicId, $client456->toString());
        self::assertCount(1, $animalsFor456);
        self::assertSame('Max', $animalsFor456[0]->name());
    }

    public function testFindByActiveOwnerExcludesEndedOwnerships(): void
    {
        $clinicUuid = Uuid::v7();
        $clinicId   = ClinicId::fromString($clinicUuid->toString());
        $client123  = Uuid::v7();

        $animal = AnimalEntityFactory::createOne([
            'clinicId' => $clinicUuid,
            'name'     => 'Rex',
        ]);

        OwnershipEntityFactory::createOne([
            'animal'   => $animal,
            'clientId' => $client123,
            'role'     => OwnershipRole::PRIMARY,
            'status'   => OwnershipStatus::ENDED,
            'endedAt'  => new \DateTimeImmutable('-1 day'),
        ]);

        /** @var AnimalRepositoryInterface $repo */
        $repo = static::getContainer()->get(AnimalRepositoryInterface::class);

        $animals = $repo->findByActiveOwner($clinicId, $client123->toString());
        self::assertCount(0, $animals);
    }

    public function testFindByActiveOwnerInDifferentClinic(): void
    {
        $clinic1Uuid = Uuid::v7();
        $clinic2Uuid = Uuid::v7();
        $clinic1Id   = ClinicId::fromString($clinic1Uuid->toString());
        $clinic2Id   = ClinicId::fromString($clinic2Uuid->toString());
        $client123   = Uuid::v7();

        $animal = AnimalEntityFactory::createOne([
            'clinicId' => $clinic1Uuid,
            'name'     => 'Rex',
        ]);

        OwnershipEntityFactory::createOne([
            'animal'   => $animal,
            'clientId' => $client123,
            'role'     => OwnershipRole::PRIMARY,
            'status'   => OwnershipStatus::ACTIVE,
        ]);

        /** @var AnimalRepositoryInterface $repo */
        $repo = static::getContainer()->get(AnimalRepositoryInterface::class);

        $animalsClinic1 = $repo->findByActiveOwner($clinic1Id, $client123->toString());
        self::assertCount(1, $animalsClinic1);

        $animalsClinic2 = $repo->findByActiveOwner($clinic2Id, $client123->toString());
        self::assertCount(0, $animalsClinic2);
    }
}
