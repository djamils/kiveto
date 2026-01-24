<?php

declare(strict_types=1);

namespace App\Animal\Application\Command\CreateAnimal;

use App\Animal\Domain\Animal;
use App\Animal\Domain\Enum\LifeStatus;
use App\Animal\Domain\Enum\RegistryType;
use App\Animal\Domain\Enum\ReproductiveStatus;
use App\Animal\Domain\Enum\Sex;
use App\Animal\Domain\Enum\Species;
use App\Animal\Domain\Enum\TransferStatus;
use App\Animal\Domain\Exception\MicrochipAlreadyUsed;
use App\Animal\Domain\Port\AnimalRepositoryInterface;
use App\Animal\Domain\ValueObject\AuxiliaryContact;
use App\Animal\Domain\ValueObject\Identification;
use App\Animal\Domain\ValueObject\LifeCycle;
use App\Animal\Domain\ValueObject\Transfer;
use App\Clinic\Domain\ValueObject\ClinicId;
use App\Shared\Application\Bus\EventBusInterface;
// CommandHandlerInterface removed - Symfony handles it via AsMessageHandler
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

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
        $animalId = $this->repository->nextId();
        $now      = $this->clock->now();

        // Check microchip uniqueness if provided
        if (null !== $command->microchipNumber && '' !== $command->microchipNumber) {
            if ($this->repository->existsMicrochip($clinicId, $command->microchipNumber)) {
                throw MicrochipAlreadyUsed::create($command->microchipNumber, $command->clinicId);
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
