<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Patient\Application\Query\GetPatientAnimalLink;

use App\Context\Patient\Application\Port\PatientReadRepositoryInterface;
use App\Context\Patient\Application\Query\GetPatientAnimalLink\GetPatientAnimalLink;
use App\Context\Patient\Application\Query\GetPatientAnimalLink\GetPatientAnimalLinkHandler;
use App\Context\Patient\Application\Query\GetPatientAnimalLink\PatientAnimalLinkDto;
use PHPUnit\Framework\TestCase;

final class GetPatientAnimalLinkHandlerTest extends TestCase
{
    private const string PATIENT_ID = '01950000-0000-7000-0000-000000000010';
    private const string CLINIC_ID  = '01950000-0000-7000-0000-000000000020';
    private const string ANIMAL_ID  = '01950000-0000-7000-0000-000000000030';

    public function testReturnsDtoFromReadRepository(): void
    {
        $dto = new PatientAnimalLinkDto(
            patientId: self::PATIENT_ID,
            animalId: self::ANIMAL_ID,
            displayLabel: 'Luna',
            observedSpecies: null,
            observedColor: null,
        );

        $readRepository = $this->createMock(PatientReadRepositoryInterface::class);
        $readRepository
            ->expects(self::once())
            ->method('findAnimalLink')
            ->with(self::CLINIC_ID, self::PATIENT_ID)
            ->willReturn($dto)
        ;

        $handler = new GetPatientAnimalLinkHandler($readRepository);

        $result = $handler(new GetPatientAnimalLink(patientId: self::PATIENT_ID, clinicId: self::CLINIC_ID));

        self::assertSame($dto, $result);
    }

    public function testReturnsNullWhenPatientNotFound(): void
    {
        $readRepository = $this->createStub(PatientReadRepositoryInterface::class);
        $readRepository->method('findAnimalLink')->willReturn(null);

        $handler = new GetPatientAnimalLinkHandler($readRepository);

        self::assertNull($handler(new GetPatientAnimalLink(patientId: self::PATIENT_ID, clinicId: self::CLINIC_ID)));
    }
}
