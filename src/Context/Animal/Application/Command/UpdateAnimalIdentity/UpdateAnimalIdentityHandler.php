<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Command\UpdateAnimalIdentity;

use App\Context\Animal\Domain\Exception\AnimalClinicMismatchException;
use App\Context\Animal\Domain\Exception\MicrochipAlreadyUsedException;
use App\Context\Animal\Domain\Repository\AnimalRepositoryInterface;
use App\Context\Animal\Domain\ValueObject\AnimalId;
use App\Context\Animal\Domain\ValueObject\AuxiliaryContact;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use App\Context\Animal\Domain\ValueObject\Identification;
use App\Context\Animal\Domain\ValueObject\RegistryType;
use App\Context\Animal\Domain\ValueObject\ReproductiveStatus;
use App\Context\Animal\Domain\ValueObject\Sex;
use App\Context\Animal\Domain\ValueObject\Species;
use App\Shared\Application\Bus\EventBusInterface;
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
            throw new AnimalClinicMismatchException(
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

            if ($this->repository->existsByMicrochip($clinicId, $command->microchipNumber, $animalId)) {
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
            registryReference: $command->registryReference,
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
