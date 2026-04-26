<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Patient;

use App\Context\Patient\Application\Port\PatientReadRepositoryInterface;
use App\Context\Patient\Application\Service\PatientCreationService;
use App\Context\Patient\Domain\Exception\PatientAlreadyExistsForAnimalException;
use App\Context\Patient\Domain\Repository\PatientRepositoryInterface;
use App\Context\Patient\Domain\ValueObject\ClinicId;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class PatientCreationServiceTest extends TestCase
{
    private PatientRepositoryInterface&MockObject $patientRepository;
    private PatientReadRepositoryInterface&MockObject $patientReadRepository;
    private UuidGeneratorInterface&MockObject $uuidGenerator;
    private ClockInterface&MockObject $clock;
    private PatientCreationService $service;

    protected function setUp(): void
    {
        $this->patientRepository     = $this->createMock(PatientRepositoryInterface::class);
        $this->patientReadRepository = $this->createMock(PatientReadRepositoryInterface::class);
        $this->uuidGenerator         = $this->createMock(UuidGeneratorInterface::class);
        $this->clock                 = $this->createMock(ClockInterface::class);

        $eventBus = $this->createStub(EventBusInterface::class);

        $this->service = new PatientCreationService(
            patientRepository: $this->patientRepository,
            patientReadRepository: $this->patientReadRepository,
            uuidGenerator: $this->uuidGenerator,
            domainEventPublisher: new DomainEventPublisher($eventBus),
            clock: $this->clock,
        );
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testCreateUnidentifiedCreatesPatient(): void
    {
        $clinicId = ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');
        $now      = new \DateTimeImmutable('2024-01-01T10:00:00+00:00');
        $newUuid  = '01234567-89ab-cdef-0123-456789abcdef';

        $this->uuidGenerator->expects(self::once())->method('generate')->willReturn($newUuid);
        $this->clock->expects(self::once())->method('now')->willReturn($now);
        $this->patientRepository->expects(self::once())->method('save');

        $patientId = $this->service->createUnidentified(
            clinicId: $clinicId,
            provisionalLabel: 'Chat roux',
            observedSpecies: 'cat',
            observedColor: 'roux',
        );

        self::assertSame($newUuid, $patientId->toString());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testCreateFromAnimalThrowsIfActivePatientExistsForAnimal(): void
    {
        $clinicId = ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');
        $animalId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';

        $this->patientReadRepository
            ->expects(self::once())
            ->method('existsActiveForAnimal')
            ->with($clinicId->toString(), $animalId)
            ->willReturn(true)
        ;

        $this->patientRepository->expects(self::never())->method('save');

        $this->expectException(PatientAlreadyExistsForAnimalException::class);

        $this->service->createFromAnimal(
            clinicId: $clinicId,
            animalId: $animalId,
            animalName: 'Rex',
        );
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testCreateFromAnimalCreatesPatient(): void
    {
        $clinicId = ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');
        $animalId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $now      = new \DateTimeImmutable('2024-01-01T10:00:00+00:00');
        $newUuid  = '01234567-89ab-cdef-0123-456789abcdef';

        $this->patientReadRepository
            ->expects(self::once())
            ->method('existsActiveForAnimal')
            ->with($clinicId->toString(), $animalId)
            ->willReturn(false)
        ;

        $this->uuidGenerator->expects(self::once())->method('generate')->willReturn($newUuid);
        $this->clock->expects(self::once())->method('now')->willReturn($now);
        $this->patientRepository->expects(self::once())->method('save');

        $patientId = $this->service->createFromAnimal(
            clinicId: $clinicId,
            animalId: $animalId,
            animalName: 'Rex',
        );

        self::assertSame($newUuid, $patientId->toString());
    }
}
