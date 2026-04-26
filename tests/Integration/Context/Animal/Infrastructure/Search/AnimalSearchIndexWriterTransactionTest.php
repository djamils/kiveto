<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Animal\Infrastructure\Search;

use App\Context\Animal\Application\Search\AnimalSearchEntryData;
use App\Context\Animal\Application\Search\AnimalSearchEntryWriterInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class AnimalSearchIndexWriterTransactionTest extends KernelTestCase
{
    private Connection $conn;
    private AnimalSearchEntryWriterInterface $writer;

    protected function setUp(): void
    {
        self::bootKernel();

        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        \assert($em instanceof EntityManagerInterface);

        $writer = static::getContainer()->get(AnimalSearchEntryWriterInterface::class);
        \assert($writer instanceof AnimalSearchEntryWriterInterface);

        $this->conn   = $em->getConnection();
        $this->writer = $writer;
    }

    public function testRollbackOnExceptionAfterUpsert(): void
    {
        $animalId = Uuid::v7()->toString();
        $clinicId = '01912345-6789-7abc-8def-000000000001';

        $data = new AnimalSearchEntryData(
            animalId: $animalId,
            clinicId: $clinicId,
            animalName: 'Rollback Test Animal',
            species: 'dog',
            breedName: null,
            chipNumber: null,
            ownerName: null,
            ownerPhone: null,
            primaryOwnerClientId: null,
            status: 'active',
        );

        $this->conn->beginTransaction();
        $this->writer->upsert($data);

        $this->conn->rollBack();

        $row = $this->conn->fetchAssociative(
            'SELECT id FROM animal__search_entries WHERE id = :id',
            ['id' => Uuid::fromString($animalId)->toBinary()],
        );

        self::assertFalse($row, 'Row must not exist after rollback');
    }
}
