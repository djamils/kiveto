<?php

declare(strict_types=1);

namespace App\Animal\Application\Command\UpdateAnimalIdentity;

use App\Animal\Domain\Enum\RegistryType;
use App\Animal\Domain\Enum\ReproductiveStatus;
use App\Animal\Domain\Enum\Sex;
use App\Animal\Domain\Enum\Species;
use App\Animal\Domain\Exception\AnimalClinicMismatch;
use App\Animal\Domain\Exception\MicrochipAlreadyUsed;
use App\Animal\Domain\Repository\AnimalRepositoryInterface;
use App\Animal\Domain\ValueObject\AnimalId;
use App\Animal\Domain\ValueObject\AuxiliaryContact;
use App\Animal\Domain\ValueObject\Identification;
use App\Clinic\Domain\ValueObject\ClinicId;
use App\Shared\Application\Bus\EventBusInterface;
// CommandHandlerInterface removed - Symfony handles it via AsMessageHandler
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateAnimalIdentityHandler
{
    public function __construct(
        private AnimalRepositoryInterface $repository,
        private EventBusInterface $eventBus,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(UpdateAnimalIdentity $command): void
    {
        $clinicId = ClinicId::fromString($command->clinicId);
        $animalId = AnimalId::fromString($command->animalId);
        $now      = $this->clock->now();

        $animal = $this->repository->get($clinicId, $animalId);

        if (!$animal->clinicId()->equals($clinicId)) {
            throw AnimalClinicMismatch::create(
                $command->animalId,
                $command->clinicId,
                $animal->clinicId()->toString()
            );
        }

        // Check microchip uniqueness if changed
        $microchipChanged = null !== $command->microchipNumber
            && '' !== $command->microchipNumber
            && $command->microchipNumber !== $animal->identification()->microchipNumber;

        if ($microchipChanged) {
            \assert(null !== $command->microchipNumber);

            if ($this->repository->existsMicrochip($clinicId, $command->microchipNumber, $animalId)) {
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

        $animal->updateIdentity(
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
            auxiliaryContact: $auxiliaryContact,
            now: $now,
        );

        $this->repository->save($animal);
        $this->eventBus->publish([], ...$animal->pullDomainEvents());
    }
}
