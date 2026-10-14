<?php

declare(strict_types=1);

namespace App\Context\Animal\Infrastructure\Persistence\Doctrine;

use App\Context\Animal\Application\Port\WeightRecordReadRepositoryInterface;
use App\Context\Animal\Application\Query\ListAnimalWeightRecords\WeightRecordView;
use App\Context\Animal\Domain\ValueObject\AnimalId;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use App\Context\Animal\Infrastructure\Persistence\Doctrine\Entity\WeightRecordEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineWeightRecordReadRepository implements WeightRecordReadRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function listForAnimal(ClinicId $clinicId, AnimalId $animalId): array
    {
        $entities = $this->em->getRepository(WeightRecordEntity::class)->findBy(
            [
                'clinicId' => Uuid::fromString($clinicId->toString()),
                'animalId' => Uuid::fromString($animalId->toString()),
            ],
            ['measuredOn' => 'DESC', 'recordedAt' => 'DESC', 'id' => 'DESC'],
        );

        return array_map(
            static fn (WeightRecordEntity $entity): WeightRecordView => new WeightRecordView(
                id: $entity->id->toString(),
                weightGrams: $entity->weightGrams,
                bodyConditionScore: $entity->bodyConditionScore,
                measuredOn: $entity->measuredOn,
                authorName: $entity->authorName,
            ),
            $entities,
        );
    }
}
