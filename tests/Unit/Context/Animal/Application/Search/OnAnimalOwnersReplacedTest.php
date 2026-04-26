<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Animal\Application\Search;

use App\Context\Animal\Application\Search\AnimalSearchEntryWriterInterface;
use App\Context\Animal\Application\Search\OnAnimalOwnersReplaced;
use App\Context\Animal\Domain\Event\AnimalOwnersReplaced;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class OnAnimalOwnersReplacedTest extends TestCase
{
    public function testItUpdatesOwnerInIndexWithFetchedNameAndPhone(): void
    {
        $animalId = '01912345-6789-7abc-8def-000000000001';
        $clinicId = '01912345-6789-7abc-8def-000000000002';
        $ownerId  = '01912345-6789-7abc-8def-000000000003';

        $conn = $this->createStub(Connection::class);
        $conn->method('fetchAssociative')
            ->willReturnOnConsecutiveCalls(
                ['name' => 'Jean Dupont'],
                ['value' => '0612345678'],
            )
        ;

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($conn);

        $writer = $this->createMock(AnimalSearchEntryWriterInterface::class);
        $writer->expects(self::once())
            ->method('updateOwner')
            ->with($animalId, $clinicId, $ownerId, 'Jean Dupont', '0612345678')
        ;

        $event   = new AnimalOwnersReplaced($animalId, $clinicId, $ownerId, []);
        $handler = new OnAnimalOwnersReplaced($writer, $em);
        $handler($event);
    }
}
