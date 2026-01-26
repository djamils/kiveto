<?php

declare(strict_types=1);

namespace App\Animal\Application\Command\CreateAnimal;

use App\Animal\Domain\Animal;
use App\Animal\Domain\Exception\MicrochipAlreadyUsedException;
use App\Animal\Domain\Repository\AnimalRepositoryInterface;
use App\Animal\Domain\ValueObject\AnimalId;
use App\Animal\Domain\ValueObject\AuxiliaryContact;
use App\Animal\Domain\ValueObject\Identification;
use App\Animal\Domain\ValueObject\LifeCycle;
use App\Animal\Domain\ValueObject\LifeStatus;
use App\Animal\Domain\ValueObject\RegistryType;
use App\Animal\Domain\ValueObject\ReproductiveStatus;
use App\Animal\Domain\ValueObject\Sex;
use App\Animal\Domain\ValueObject\Species;
use App\Animal\Domain\ValueObject\Transfer;
use App\Animal\Domain\ValueObject\TransferStatus;
use App\Clinic\Domain\ValueObject\ClinicId;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

// CommandHandlerInterface removed - Symfony handles it via AsMessageHandler

#[AsMessageHandler]
final readonly class CreateAnimalHandler
{
    public function __construct(
        private AnimalRepositoryInterface $repository,
        private EventBusInterface $eventBus,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(CreateAnimal $command): string
    {
        $clinicId = ClinicId::fromString($command->clinicId);
        $animalId = AnimalId::fromString(Uuid::v7()->toString());
        $now      = $this->clock->now();

        // Check microchip uniqueness if provided
        if (null !== $command->microchipNumber && '' !== $command->microchipNumber) {
            if ($this->repository->existsByMicrochip($clinicId, $command->microchipNumber)) {
                throw new MicrochipAlreadyUsedException($command->microchipNumber, $command->clinicId);
            }
        }

        // Build identification
        $identification = new Identification(
            microchipNumber: $command->microchipNumber,
            tattooNumber: $command->tattooNumber,
            passportNumber: $command->passportNumber,
            registryType: RegistryType::from($command->registryType),
            registryNumber: $command->registryNumber,
            sireNumber: $command->sireNumber,
        );

        // Build lifecycle
        $lifeCycle = new LifeCycle(
            lifeStatus: LifeStatus::from($command->lifeStatus),
            deceasedAt: $command->deceasedAt ? new \DateTimeImmutable($command->deceasedAt) : null,
            missingSince: $command->missingSince ? new \DateTimeImmutable($command->missingSince) : null,
        );

        // Build transfer
        $transfer = new Transfer(
            transferStatus: TransferStatus::from($command->transferStatus),
            soldAt: $command->soldAt ? new \DateTimeImmutable($command->soldAt) : null,
            givenAt: $command->givenAt ? new \DateTimeImmutable($command->givenAt) : null,
        );

        // Build auxiliary contact
        $auxiliaryContact    = null;
        $hasAuxiliaryContact = null !== $command->auxiliaryContactFirstName
            && null !== $command->auxiliaryContactLastName
            && null !== $command->auxiliaryContactPhoneNumber;

        if ($hasAuxiliaryContact) {
            \assert(null !== $command->auxiliaryContactFirstName);
            \assert(null !== $command->auxiliaryContactLastName);
            \assert(null !== $command->auxiliaryContactPhoneNumber);

            $auxiliaryContact = new AuxiliaryContact(
                firstName: $command->auxiliaryContactFirstName,
                lastName: $command->auxiliaryContactLastName,
                phoneNumber: $command->auxiliaryContactPhoneNumber,
            );
        }

        // Create animal
        $animal = Animal::create(
            id: $animalId,
            clinicId: $clinicId,
            name: $command->name,
            species: Species::from($command->species),
            sex: Sex::from($command->sex),
            reproductiveStatus: ReproductiveStatus::from($command->reproductiveStatus),
            isMixedBreed: $command->isMixedBreed,
            breedName: $command->breedName,
            birthDate: $command->birthDate ? new \DateTimeImmutable($command->birthDate) : null,
            color: $command->color,
            photoUrl: $command->photoUrl,
            identification: $identification,
            lifeCycle: $lifeCycle,
            transfer: $transfer,
            auxiliaryContact: $auxiliaryContact,
            primaryOwnerClientId: $command->primaryOwnerClientId,
            secondaryOwnerClientIds: $command->secondaryOwnerClientIds,
            now: $now,
        );

        $this->repository->save($animal);
        $this->eventBus->publish([], ...$animal->pullDomainEvents());

        return $animalId->value();
    }
}
