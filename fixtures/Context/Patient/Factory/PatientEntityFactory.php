<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Patient\Factory;

use App\Context\Patient\Domain\ValueObject\DisplayLabelKind;
use App\Context\Patient\Domain\ValueObject\PatientStatus;
use App\Context\Patient\Infrastructure\Persistence\Doctrine\Entity\PatientEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<PatientEntity>
 */
final class PatientEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return PatientEntity::class;
    }

    public function withId(string $id): self
    {
        return $this->afterInstantiate(function (PatientEntity $entity) use ($id): void {
            $entity->setId(Uuid::fromString($id));
        });
    }

    public function withClinicId(string $clinicId): self
    {
        return $this->afterInstantiate(function (PatientEntity $entity) use ($clinicId): void {
            $entity->setClinicId(Uuid::fromString($clinicId));
        });
    }

    public function withAnimalLinkId(string $animalId): self
    {
        return $this->afterInstantiate(function (PatientEntity $entity) use ($animalId): void {
            $entity->setAnimalLinkId(Uuid::fromString($animalId));
        });
    }

    /**
     * Patient linked to a known animal record — displayLabelKind set to FromAnimal.
     */
    public function identified(): self
    {
        $animalId = Uuid::v7();

        return $this->afterInstantiate(function (PatientEntity $entity) use ($animalId): void {
            $entity->setDisplayLabelKind(DisplayLabelKind::FromAnimal);
            $entity->setAnimalLinkId($animalId);
        });
    }

    /**
     * Patient with no animal link — provisional label supplied by caller.
     */
    public function provisional(string $label): self
    {
        return $this->afterInstantiate(function (PatientEntity $entity) use ($label): void {
            $entity->setDisplayLabelKind(DisplayLabelKind::Provisional);
            $entity->setAnimalLinkId(null);
            $entity->setDisplayLabelValue($label);
        });
    }

    public function active(): self
    {
        return $this->afterInstantiate(function (PatientEntity $entity): void {
            $entity->setStatus(PatientStatus::Active);
        });
    }

    public function archived(): self
    {
        return $this->afterInstantiate(function (PatientEntity $entity): void {
            $entity->setStatus(PatientStatus::Archived);
        });
    }

    protected function defaults(): array|callable
    {
        return [
            'id'                => Uuid::v7(),
            'clinicId'          => Uuid::v7(),
            'status'            => PatientStatus::Active,
            'displayLabelKind'  => DisplayLabelKind::Provisional,
            'displayLabelValue' => self::faker()->words(3, true),
            'animalLinkId'      => null,
            'observedSpecies'   => null,
            'observedColor'     => null,
            'version'           => 1,
            'createdAt'         => \DateTimeImmutable::createFromMutable(
                self::faker()->dateTimeBetween('-6 months')
            ),
            'updatedAt' => \DateTimeImmutable::createFromMutable(
                self::faker()->dateTimeBetween('-1 month')
            ),
        ];
    }
}
