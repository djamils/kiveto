<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Patient;

use App\Context\Patient\Application\Command\ReconcilePatientToAnimal\ReconcilePatientToAnimal;
use App\Context\Patient\Application\Command\ReconcilePatientToAnimal\ReconcilePatientToAnimalHandler;
use App\Context\Patient\Application\Port\PatientReadRepositoryInterface;
use App\Context\Patient\Application\Service\PatientIdentityResolutionService;
use App\Context\Patient\Domain\Patient;
use App\Context\Patient\Domain\Repository\PatientRepositoryInterface;
use App\Context\Patient\Domain\ValueObject\ClinicId;
use App\Context\Patient\Domain\ValueObject\PatientId;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Bus\IntegrationEventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Application\Event\IntegrationEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

final class ReconcilePatientToAnimalHandlerTest extends TestCase
{
    private PatientRepositoryInterface&MockObject $patientRepository;
    private PatientReadRepositoryInterface&Stub $patientReadRepository;
    private ClockInterface&Stub $clock;
    private ReconcilePatientToAnimalHandler $handler;

    protected function setUp(): void
    {
        $this->patientRepository     = $this->createMock(PatientRepositoryInterface::class);
        $this->patientReadRepository = $this->createStub(PatientReadRepositoryInterface::class);
        $this->clock                 = $this->createStub(ClockInterface::class);

        $service = new PatientIdentityResolutionService(
            patientRepository: $this->patientRepository,
            patientReadRepository: $this->patientReadRepository,
            domainEventPublisher: new DomainEventPublisher($this->createStub(EventBusInterface::class)),
            integrationEventPublisher: new IntegrationEventPublisher(
                $this->createStub(IntegrationEventBusInterface::class),
            ),
            clock: $this->clock,
        );

        $this->handler = new ReconcilePatientToAnimalHandler($service);
    }

    public function testRoutesSourcePatientIdFromAdmissionToService(): void
    {
        $sourcePatientId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $animalId        = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';
        $animalName      = 'Max';
        $clinicId        = '12345678-9abc-def0-1234-56789abcdef0';
        $now             = new \DateTimeImmutable('2024-06-01T10:00:00+00:00');

        $this->clock->method('now')->willReturn($now);

        // No existing patient for the animal — takes the linkToExistingAnimal path
        $this->patientReadRepository
            ->method('getActivePatientIdForAnimal')
            ->willReturn(null)
        ;

        $patient = Patient::createUnidentified(
            id: PatientId::fromString($sourcePatientId),
            clinicId: ClinicId::fromString($clinicId),
            provisionalLabel: 'Inconnu',
            observedSpecies: null,
            observedColor: null,
            now: $now,
        );

        // The service calls patientRepository->get with the sourcePatientId
        $this->patientRepository
            ->expects(self::once())
            ->method('get')
            ->with(
                self::callback(static fn (ClinicId $id): bool => $id->toString() === $clinicId),
                self::callback(static fn (PatientId $id): bool => $id->toString() === $sourcePatientId),
            )
            ->willReturn($patient)
        ;

        $this->patientRepository->expects(self::once())->method('save');

        ($this->handler)(new ReconcilePatientToAnimal(
            sourcePatientId: $sourcePatientId,
            animalId: $animalId,
            animalName: $animalName,
            clinicId: $clinicId,
        ));
    }
}
