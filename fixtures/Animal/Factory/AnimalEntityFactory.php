<?php

declare(strict_types=1);

namespace App\Fixtures\Animal\Factory;

use App\Animal\Domain\ValueObject\AnimalStatus;
use App\Animal\Domain\ValueObject\LifeStatus;
use App\Animal\Domain\ValueObject\RegistryType;
use App\Animal\Domain\ValueObject\ReproductiveStatus;
use App\Animal\Domain\ValueObject\Sex;
use App\Animal\Domain\ValueObject\Species;
use App\Animal\Domain\ValueObject\TransferStatus;
use App\Animal\Infrastructure\Persistence\Doctrine\Entity\AnimalEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<AnimalEntity>
 */
final class AnimalEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return AnimalEntity::class;
    }

    public function withId(string $id): self
    {
        return $this->afterInstantiate(function (AnimalEntity $animal) use ($id): void {
            $animal->setId(Uuid::fromString($id));
        });
    }

    public function withClinicId(string $clinicId): self
    {
        return $this->afterInstantiate(function (AnimalEntity $animal) use ($clinicId): void {
            $animal->setClinicId(Uuid::fromString($clinicId));
        });
    }

    public function withName(string $name): self
    {
        return $this->afterInstantiate(function (AnimalEntity $animal) use ($name): void {
            $animal->setName($name);
        });
    }

    public function dog(): self
    {
        /** @var string $breedName */
        $breedName = self::faker()->randomElement([
            'Labrador',
            'Berger Allemand',
            'Golden Retriever',
            'Bouledogue Français',
            'Beagle',
            'Caniche',
            'Yorkshire Terrier',
        ]);

        return $this->afterInstantiate(function (AnimalEntity $animal) use ($breedName): void {
            $animal->setSpecies(Species::DOG);
            $animal->setBreedName($breedName);
        });
    }

    public function cat(): self
    {
        /** @var string $breedName */
        $breedName = self::faker()->randomElement([
            'Européen',
            'Siamois',
            'Persan',
            'Maine Coon',
            'Chartreux',
            'Bengale',
        ]);

        return $this->afterInstantiate(function (AnimalEntity $animal) use ($breedName): void {
            $animal->setSpecies(Species::CAT);
            $animal->setBreedName($breedName);
        });
    }

    public function male(): self
    {
        return $this->afterInstantiate(function (AnimalEntity $animal): void {
            $animal->setSex(Sex::MALE);
        });
    }

    public function female(): self
    {
        return $this->afterInstantiate(function (AnimalEntity $animal): void {
            $animal->setSex(Sex::FEMALE);
        });
    }

    public function neutered(): self
    {
        return $this->afterInstantiate(function (AnimalEntity $animal): void {
            $animal->setReproductiveStatus(ReproductiveStatus::NEUTERED);
        });
    }

    public function intact(): self
    {
        return $this->afterInstantiate(function (AnimalEntity $animal): void {
            $animal->setReproductiveStatus(ReproductiveStatus::INTACT);
        });
    }

    public function active(): self
    {
        return $this->afterInstantiate(function (AnimalEntity $animal): void {
            $animal->setStatus(AnimalStatus::ACTIVE);
        });
    }

    public function archived(): self
    {
        return $this->afterInstantiate(function (AnimalEntity $animal): void {
            $animal->setStatus(AnimalStatus::ARCHIVED);
        });
    }

    public function alive(): self
    {
        return $this->afterInstantiate(function (AnimalEntity $animal): void {
            $animal->setLifeStatus(LifeStatus::ALIVE);
            $animal->setDeceasedAt(null);
            $animal->setMissingSince(null);
        });
    }

    public function deceased(): self
    {
        $deceasedAt = \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween('-1 year'));

        return $this->afterInstantiate(function (AnimalEntity $animal) use ($deceasedAt): void {
            $animal->setLifeStatus(LifeStatus::DECEASED);
            $animal->setDeceasedAt($deceasedAt);
            $animal->setMissingSince(null);
        });
    }

    public function withMicrochip(?string $microchipNumber = null): self
    {
        $microchip = $microchipNumber ?? self::faker()->numerify('250269#########');

        return $this->afterInstantiate(function (AnimalEntity $animal) use ($microchip): void {
            $animal->setMicrochipNumber($microchip);
        });
    }

    public function withColor(string $color): self
    {
        return $this->afterInstantiate(function (AnimalEntity $animal) use ($color): void {
            $animal->setColor($color);
        });
    }

    public function withBreed(string $breedName, Species $species): self
    {
        return $this->afterInstantiate(function (AnimalEntity $animal) use ($breedName, $species): void {
            $animal->setSpecies($species);
            $animal->setBreedName($breedName);
        });
    }

    protected function defaults(): array|callable
    {
        /** @var Species $species */
        $species = self::faker()->randomElement([Species::DOG, Species::CAT, Species::NAC]);
        /** @var Sex $sex */
        $sex = self::faker()->randomElement([Sex::MALE, Sex::FEMALE, Sex::UNKNOWN]);
        /** @var ReproductiveStatus $reproductiveStatus */
        $reproductiveStatus = self::faker()->randomElement([
            ReproductiveStatus::INTACT,
            ReproductiveStatus::NEUTERED,
            ReproductiveStatus::UNKNOWN,
        ]);
        /** @var LifeStatus $lifeStatus */
        $lifeStatus = self::faker()->randomElement([
            LifeStatus::ALIVE,
            LifeStatus::ALIVE,
            LifeStatus::ALIVE,
            LifeStatus::DECEASED,
        ]);
        /** @var AnimalStatus $animalStatus */
        $animalStatus = self::faker()->randomElement([
            AnimalStatus::ACTIVE,
            AnimalStatus::ACTIVE,
            AnimalStatus::ACTIVE,
            AnimalStatus::ARCHIVED,
        ]);

        $breedName = null;
        if (Species::DOG === $species) {
            $breedName = self::faker()->randomElement(['Labrador', 'Berger Allemand', 'Caniche', 'Beagle']);
        } elseif (Species::CAT === $species) {
            $breedName = self::faker()->randomElement(['Européen', 'Siamois', 'Persan', 'Maine Coon']);
        }

        $colors = ['Noir', 'Blanc', 'Marron', 'Gris', 'Roux', 'Tigré', 'Tricolore'];

        return [
            'id'                          => Uuid::v7(),
            'clinicId'                    => Uuid::v7(),
            'name'                        => self::faker()->firstName(),
            'species'                     => $species,
            'sex'                         => $sex,
            'reproductiveStatus'          => $reproductiveStatus,
            'isMixedBreed'                => self::faker()->boolean(30),
            'breedName'                   => $breedName,
            'birthDate'                   => self::faker()->optional(0.8)->dateTimeBetween('-15 years', '-2 months'),
            'color'                       => self::faker()->randomElement($colors),
            'photoUrl'                    => null,
            'microchipNumber'             => self::faker()->optional(0.6)->numerify('250269#########'),
            'tattooNumber'                => null,
            'passportNumber'              => null,
            'registryType'                => RegistryType::NONE,
            'registryNumber'              => null,
            'sireNumber'                  => null,
            'lifeStatus'                  => $lifeStatus,
            'deceasedAt'                  => null,
            'missingSince'                => null,
            'transferStatus'              => TransferStatus::NONE,
            'soldAt'                      => null,
            'givenAt'                     => null,
            'auxiliaryContactFirstName'   => null,
            'auxiliaryContactLastName'    => null,
            'auxiliaryContactPhoneNumber' => null,
            'status'                      => $animalStatus,
            'createdAt'                   => \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween(
                '-2 years',
                '-1 month'
            )),
            'updatedAt' => \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween('-1 month')),
        ];
    }
}
