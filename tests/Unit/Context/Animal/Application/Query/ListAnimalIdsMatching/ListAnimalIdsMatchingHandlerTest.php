<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Animal\Application\Query\ListAnimalIdsMatching;

use App\Context\Animal\Application\Port\AnimalReadRepositoryInterface;
use App\Context\Animal\Application\Query\ListAnimalIdsMatching\ListAnimalIdsMatching;
use App\Context\Animal\Application\Query\ListAnimalIdsMatching\ListAnimalIdsMatchingHandler;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use PHPUnit\Framework\TestCase;

final class ListAnimalIdsMatchingHandlerTest extends TestCase
{
    private const string CLINIC_ID = '22222222-2222-4222-8222-222222222222';
    private const string ANIMAL_ID = '11111111-1111-4111-8111-111111111111';

    public function testTheSearchTermAndSpeciesAreForwardedAndTheIdsReturnedAsIs(): void
    {
        $readRepository = $this->createMock(AnimalReadRepositoryInterface::class);
        $readRepository
            ->expects(self::once())
            ->method('findIdsMatching')
            ->with(self::equalTo(ClinicId::fromString(self::CLINIC_ID)), 'Rex', 'dog')
            ->willReturn([self::ANIMAL_ID])
        ;

        $handler = new ListAnimalIdsMatchingHandler($readRepository);

        $result = $handler(new ListAnimalIdsMatching(self::CLINIC_ID, 'Rex', 'dog'));

        self::assertSame([self::ANIMAL_ID], $result);
    }

    public function testTheQueryDefaultsAreForwardedAsNullAndNoMatchReturnsAnEmptyList(): void
    {
        $readRepository = $this->createMock(AnimalReadRepositoryInterface::class);
        $readRepository
            ->expects(self::once())
            ->method('findIdsMatching')
            ->with(self::equalTo(ClinicId::fromString(self::CLINIC_ID)), null, null)
            ->willReturn([])
        ;

        $handler = new ListAnimalIdsMatchingHandler($readRepository);

        self::assertSame([], $handler(new ListAnimalIdsMatching(self::CLINIC_ID)));
    }
}
