<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Animal\Infrastructure\Search;

use App\Context\Animal\Application\Search\AnimalSearchEntryData;
use App\Context\Animal\Infrastructure\Persistence\Doctrine\Entity\SearchEntryEntity;
use App\Context\Animal\Infrastructure\Search\AnimalSearchEntryWriter;
use App\Shared\Search\Normalization\SearchTermNormalizer;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class AnimalSearchIndexWriterTest extends TestCase
{
    public function testUpsertInsertsWhenNotExists(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn(null);
        $em->expects(self::once())->method('persist');
        $em->expects(self::once())->method('flush');

        $writer = new AnimalSearchEntryWriter($em, new SearchTermNormalizer());
        $writer->upsert($this->makeData());
    }

    public function testUpsertUpdatesWhenExists(): void
    {
        $entity = $this->createMock(SearchEntryEntity::class);
        $entity->method('getClinicId')
            ->willReturn(Uuid::fromString('01912345-6789-7abc-8def-000000000002'))
        ;
        $entity->expects(self::once())->method('setAnimalName');
        $entity->expects(self::once())->method('setSearchName');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn($entity);
        $em->expects(self::never())->method('persist');
        $em->expects(self::once())->method('flush');

        $writer = new AnimalSearchEntryWriter($em, new SearchTermNormalizer());
        $writer->upsert(new AnimalSearchEntryData(
            animalId: '01912345-6789-7abc-8def-000000000001',
            clinicId: '01912345-6789-7abc-8def-000000000002',
            animalName: 'Rex Updated',
            species: 'dog',
            breedName: null,
            chipNumber: null,
            ownerName: null,
            ownerPhone: null,
            primaryOwnerClientId: null,
            status: 'active',
        ));
    }

    public function testMarkArchivedSetsStatusAndFlushes(): void
    {
        $animalId = '01912345-6789-7abc-8def-000000000001';
        $clinicId = '01912345-6789-7abc-8def-000000000002';

        $entity = $this->createMock(SearchEntryEntity::class);
        $entity->method('getClinicId')->willReturn(Uuid::fromString($clinicId));
        $entity->expects(self::once())->method('setStatus')->with('archived');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn($entity);
        $em->expects(self::once())->method('flush');

        $writer = new AnimalSearchEntryWriter($em, new SearchTermNormalizer());
        $writer->markArchived($animalId, $clinicId);
    }

    public function testFlushCalledForOrmMethods(): void
    {
        $animalId = '01912345-6789-7abc-8def-000000000001';
        $clinicId = '01912345-6789-7abc-8def-000000000002';

        $entity = $this->createStub(SearchEntryEntity::class);
        $entity->method('getClinicId')->willReturn(Uuid::fromString($clinicId));

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn($entity);
        $em->expects(self::exactly(4))->method('flush');

        $writer = new AnimalSearchEntryWriter($em, new SearchTermNormalizer());
        $writer->updateName($animalId, $clinicId, 'New Name');
        $writer->updateChip($animalId, $clinicId, '250269802120045');
        $writer->updateOwner($animalId, $clinicId, null, null);
        $writer->markArchived($animalId, $clinicId);
    }

    public function testFlushNotCalledForUpdateOwnerName(): void
    {
        $conn = $this->createStub(Connection::class);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($conn);
        $em->expects(self::never())->method('flush');

        $writer = new AnimalSearchEntryWriter($em, new SearchTermNormalizer());
        $writer->updateOwnerName(
            '01912345-6789-7abc-8def-000000000003',
            '01912345-6789-7abc-8def-000000000002',
            'Jean Dupont',
        );
    }

    private function makeData(string $animalId = '01912345-6789-7abc-8def-000000000001'): AnimalSearchEntryData
    {
        return new AnimalSearchEntryData(
            animalId: $animalId,
            clinicId: '01912345-6789-7abc-8def-000000000002',
            animalName: 'Rex',
            species: 'dog',
            breedName: 'Labrador',
            chipNumber: '250269802120045',
            ownerName: 'Jean Dupont',
            ownerPhone: '0612345678',
            primaryOwnerClientId: '01912345-6789-7abc-8def-000000000003',
            status: 'active',
        );
    }
}
