<?php

declare(strict_types=1);

namespace App\Fixtures\Animal\Factory;

use App\Animal\Domain\Enum\AnimalStatus;
use App\Animal\Domain\Enum\LifeStatus;
use App\Animal\Domain\Enum\RegistryType;
use App\Animal\Domain\Enum\ReproductiveStatus;
use App\Animal\Domain\Enum\Sex;
use App\Animal\Domain\Enum\Species;
use App\Animal\Domain\Enum\TransferStatus;
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
        return $this->with(['id' => $id]);
    }

    public function withClinicId(string $clinicId): self
    {
        return $this->with(['clinicId' => $clinicId]);
    }

    public function withName(string $name): self
    {
        return $this->with(['name' => $name]);
    }

    public function dog(): self
    {
        return $this->with([
            'species'   => Species::DOG->value,
            'breedName' => self::faker()->randomElement([
                'Labrador',
                'Berger Allemand',
                'Golden Retriever',
                'Bouledogue Français',
                'Beagle',
                'Caniche',
                'Yorkshire Terrier',
            ]),
        ]);
    }

    public function cat(): self
    {
        return $this->with([
            'species'   => Species::CAT->value,
            'breedName' => self::faker()->randomElement([
                'Européen',
                'Siamois',
                'Persan',
                'Maine Coon',
                'Chartreux',
                'Bengale',
            ]),
        ]);
    }

    public function male(): self
    {
        return $this->with(['sex' => Sex::MALE->value]);
    }

    public function female(): self
    {
        return $this->with(['sex' => Sex::FEMALE->value]);
    }

    public function neutered(): self
    {
        return $this->with(['reproductiveStatus' => ReproductiveStatus::NEUTERED->value]);
    }

    public function intact(): self
    {
        return $this->with(['reproductiveStatus' => ReproductiveStatus::INTACT->value]);
    }

    public function active(): self
    {
        return $this->with(['status' => AnimalStatus::ACTIVE->value]);
    }

    public function archived(): self
    {
        return $this->with(['status' => AnimalStatus::ARCHIVED->value]);
    }

    public function alive(): self
    {
        return $this->with([
            'lifeStatus'   => LifeStatus::ALIVE->value,
            'deceasedAt'   => null,
            'missingSince' => null,
        ]);
    }

    public function deceased(): self
    {
        return $this->with([
            'lifeStatus'   => LifeStatus::DECEASED->value,
            'deceasedAt'   => \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween('-1 year')),
            'missingSince' => null,
        ]);
    }

    public function withMicrochip(?string $microchipNumber = null): self
    {
        return $this->with([
            'microchipNumber' => $microchipNumber ?? self::faker()->numerify('250269#########'),
        ]);
    }

    public function withColor(string $color): self
    {
        return $this->with(['color' => $color]);
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
            'id'                          => Uuid::v7()->toString(),
            'clinicId'                    => Uuid::v7()->toString(),
            'name'                        => self::faker()->firstName(),
            'species'                     => $species->value,
            'sex'                         => $sex->value,
            'reproductiveStatus'          => $reproductiveStatus->value,
            'isMixedBreed'                => self::faker()->boolean(30),
            'breedName'                   => $breedName,
            'birthDate'                   => self::faker()->optional(0.8)->dateTimeBetween('-15 years', '-2 months'),
            'color'                       => self::faker()->randomElement($colors),
            'photoUrl'                    => null,
            'microchipNumber'             => self::faker()->optional(0.6)->numerify('250269#########'),
            'tattooNumber'                => null,
            'passportNumber'              => null,
            'registryType'                => RegistryType::NONE->value,
            'registryNumber'              => null,
            'sireNumber'                  => null,
            'lifeStatus'                  => $lifeStatus->value,
            'deceasedAt'                  => null,
            'missingSince'                => null,
            'transferStatus'              => TransferStatus::NONE->value,
            'soldAt'                      => null,
            'givenAt'                     => null,
            'auxiliaryContactFirstName'   => null,
            'auxiliaryContactLastName'    => null,
            'auxiliaryContactPhoneNumber' => null,
            'status'                      => $animalStatus->value,
            'createdAt'                   => \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween(
                '-2 years',
                '-1 month'
            )),
            'updatedAt' => \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween('-1 month')),
        ];
    }
}
