<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Animal\Application\Search;

use App\Context\Animal\Application\Search\AnimalSearchEntryWriterInterface;
use App\Context\Animal\Application\Search\OnAnimalArchived;
use App\Context\Animal\Domain\Event\AnimalArchived;
use PHPUnit\Framework\TestCase;

final class OnAnimalArchivedTest extends TestCase
{
    public function testItMarksAnimalArchivedInIndex(): void
    {
        $animalId = '01912345-6789-7abc-8def-000000000001';
        $clinicId = '01912345-6789-7abc-8def-000000000002';

        $writer = $this->createMock(AnimalSearchEntryWriterInterface::class);
        $writer->expects(self::once())
            ->method('markArchived')
            ->with($animalId, $clinicId)
        ;

        $event   = new AnimalArchived($animalId, $clinicId);
        $handler = new OnAnimalArchived($writer);
        $handler($event);
    }
}
