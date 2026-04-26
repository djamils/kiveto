<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Animal\Application\Search;

use App\Context\Animal\Application\Search\AnimalSearchEntryWriterInterface;
use App\Context\Animal\Application\Search\OnAnimalNameChanged;
use App\Context\Animal\Domain\Event\AnimalNameChanged;
use PHPUnit\Framework\TestCase;

final class OnAnimalNameChangedTest extends TestCase
{
    public function testItUpdatesAnimalNameInIndex(): void
    {
        $animalId = '01912345-6789-7abc-8def-000000000001';
        $clinicId = '01912345-6789-7abc-8def-000000000002';
        $newName  = 'Luna';

        $writer = $this->createMock(AnimalSearchEntryWriterInterface::class);
        $writer->expects(self::once())
            ->method('updateName')
            ->with($animalId, $clinicId, $newName)
        ;

        $event   = new AnimalNameChanged($animalId, $clinicId, $newName);
        $handler = new OnAnimalNameChanged($writer);
        $handler($event);
    }
}
