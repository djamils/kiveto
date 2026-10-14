<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Animal\Factory;

use App\Context\Animal\Domain\ValueObject\PreventiveCareKind;
use App\Context\Animal\Infrastructure\Persistence\Doctrine\Entity\PreventiveCareRecordEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<PreventiveCareRecordEntity>
 */
final class PreventiveCareRecordEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return PreventiveCareRecordEntity::class;
    }

    public function forAnimal(string $clinicId, string $animalId): self
    {
        return $this->with([
            'clinicId' => Uuid::fromString($clinicId),
            'animalId' => Uuid::fromString($animalId),
        ]);
    }

    public function act(
        PreventiveCareKind $kind,
        string $label,
        \DateTimeImmutable $administeredOn,
        ?\DateTimeImmutable $nextDueOn = null,
        ?string $batchNumber = null,
    ): self {
        return $this->with([
            'kind'           => $kind,
            'label'          => $label,
            'administeredOn' => $administeredOn->setTime(0, 0),
            'nextDueOn'      => $nextDueOn?->setTime(0, 0),
            'batchNumber'    => $batchNumber,
            'recordedAt'     => $administeredOn,
        ]);
    }

    protected function defaults(): array|callable
    {
        return [
            'id'             => Uuid::v7(),
            'clinicId'       => Uuid::v7(),
            'animalId'       => Uuid::v7(),
            'kind'           => PreventiveCareKind::VACCINATION,
            'label'          => 'Vaccin',
            'administeredOn' => new \DateTimeImmutable('today'),
            'batchNumber'    => null,
            'nextDueOn'      => null,
            'authorName'     => 'Dr Rousseau',
            'recordedAt'     => new \DateTimeImmutable(),
        ];
    }
}
