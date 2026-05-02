<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Animal\Application\Search;

use App\Context\Animal\Application\Search\AnimalSearchEntryData;
use App\Context\Animal\Application\Search\AnimalSearchEntryWriterInterface;
use App\Context\Animal\Application\Search\OnAnimalCreated;
use App\Context\Animal\Domain\Event\AnimalCreated;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class OnAnimalCreatedTest extends TestCase
{
    public function testItUpsertsAnimalIndexOnAnimalCreated(): void
    {
        $animalId = '01912345-6789-7abc-8def-000000000001';
        $clinicId = '01912345-6789-7abc-8def-000000000002';
        $ownerId  = '01912345-6789-7abc-8def-000000000003';

        $conn = $this->createStub(Connection::class);
        $conn->method('fetchAssociative')
            ->willReturnOnConsecutiveCalls(
                ['species' => 'dog', 'breed_name' => 'Labrador', 'microchip_number' => '250269802120045'],
                ['name'  => 'Jean Dupont'],
                ['value' => '0612345678'],
            )
        ;

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($conn);

        $writer = $this->createMock(AnimalSearchEntryWriterInterface::class);
        $writer->expects(self::once())
            ->method('upsert')
            ->with(self::callback(static function (AnimalSearchEntryData $data) use ($animalId, $clinicId): bool {
                return $data->animalId === $animalId
                    && $data->clinicId === $clinicId
                    && 'Rex' === $data->animalName
                    && 'dog' === $data->species
                    && 'active' === $data->status;
            }))
        ;

        $event   = new AnimalCreated($animalId, $clinicId, 'Rex', $ownerId);
        $handler = new OnAnimalCreated($writer, $em);
        $handler($event);
    }
}
