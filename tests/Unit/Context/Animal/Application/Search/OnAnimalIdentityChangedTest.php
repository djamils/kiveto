<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Animal\Application\Search;

use App\Context\Animal\Application\Search\AnimalSearchIndexWriterInterface;
use App\Context\Animal\Application\Search\OnAnimalIdentityChanged;
use App\Context\Animal\Domain\Event\AnimalIdentityChanged;
use PHPUnit\Framework\TestCase;

final class OnAnimalIdentityChangedTest extends TestCase
{
    public function testItUpdatesChipNumberInIndex(): void
    {
        $animalId = '01912345-6789-7abc-8def-000000000001';
        $clinicId = '01912345-6789-7abc-8def-000000000002';
        $chip     = '250269802120045';

        $writer = $this->createMock(AnimalSearchIndexWriterInterface::class);
        $writer->expects(self::once())
            ->method('updateChip')
            ->with($animalId, $clinicId, $chip)
        ;

        $event   = new AnimalIdentityChanged($animalId, $clinicId, $chip, null, null);
        $handler = new OnAnimalIdentityChanged($writer);
        $handler($event);
    }
}
