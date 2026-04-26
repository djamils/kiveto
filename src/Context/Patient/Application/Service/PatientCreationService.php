<?php

declare(strict_types=1);

namespace App\Context\Patient\Application\Service;

use App\Context\Patient\Application\Port\PatientReadRepositoryInterface;
use App\Context\Patient\Domain\Exception\PatientAlreadyExistsForAnimalException;
use App\Context\Patient\Domain\Patient;
use App\Context\Patient\Domain\Repository\PatientRepositoryInterface;
use App\Context\Patient\Domain\ValueObject\AnimalLink;
use App\Context\Patient\Domain\ValueObject\ClinicId;
use App\Context\Patient\Domain\ValueObject\PatientId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;

final readonly class PatientCreationService
{
    public function __construct(
        private PatientRepositoryInterface $patientRepository,
        private PatientReadRepositoryInterface $patientReadRepository,
        private UuidGeneratorInterface $uuidGenerator,
        private DomainEventPublisher $domainEventPublisher,
        private ClockInterface $clock,
    ) {
    }

    public function createUnidentified(
        ClinicId $clinicId,
        string $provisionalLabel,
        ?string $observedSpecies,
        ?string $observedColor,
    ): PatientId {
        $id  = PatientId::fromString($this->uuidGenerator->generate());
        $now = $this->clock->now();

        $patient = Patient::createUnidentified(
            id: $id,
            clinicId: $clinicId,
            provisionalLabel: $provisionalLabel,
            observedSpecies: $observedSpecies,
            observedColor: $observedColor,
            now: $now,
        );

        $this->patientRepository->save($patient);
        $this->domainEventPublisher->publish($patient);

        return $id;
    }

    public function createFromAnimal(
        ClinicId $clinicId,
        string $animalId,
        string $animalName,
    ): PatientId {
        if ($this->patientReadRepository->existsActiveForAnimal($clinicId->toString(), $animalId)) {
            throw new PatientAlreadyExistsForAnimalException($animalId, $clinicId->toString());
        }

        $id  = PatientId::fromString($this->uuidGenerator->generate());
        $now = $this->clock->now();

        $patient = Patient::createFromAnimal(
            id: $id,
            clinicId: $clinicId,
            animalLink: AnimalLink::fromAnimalId($animalId),
            animalName: $animalName,
            now: $now,
        );

        $this->patientRepository->save($patient);
        $this->domainEventPublisher->publish($patient);

        return $id;
    }
}
