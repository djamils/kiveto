<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Patient\Application\Query\HasPatientForAnimal;

use App\Context\Patient\Application\Port\PatientReadRepositoryInterface;
use App\Context\Patient\Application\Query\HasPatientForAnimal\HasPatientForAnimal;
use App\Context\Patient\Application\Query\HasPatientForAnimal\HasPatientForAnimalHandler;
use PHPUnit\Framework\TestCase;

final class HasPatientForAnimalHandlerTest extends TestCase
{
    private const string CLINIC_ID = '01950000-0000-7000-0000-000000000020';
    private const string ANIMAL_ID = '01950000-0000-7000-0000-000000000030';

    public function testAnswersWhetherAnActivePatientIsLinkedToTheAnimal(): void
    {
        $readRepository = $this->createMock(PatientReadRepositoryInterface::class);
        $readRepository
            ->expects(self::exactly(2))
            ->method('existsActiveForAnimal')
            ->with(self::CLINIC_ID, self::ANIMAL_ID)
            ->willReturnOnConsecutiveCalls(true, false)
        ;

        $handler = new HasPatientForAnimalHandler($readRepository);

        self::assertTrue($handler(new HasPatientForAnimal(self::CLINIC_ID, self::ANIMAL_ID)));
        self::assertFalse($handler(new HasPatientForAnimal(self::CLINIC_ID, self::ANIMAL_ID)));
    }
}
