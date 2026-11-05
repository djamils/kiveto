<?php

declare(strict_types=1);

namespace App\Context\Animal\Infrastructure\Persistence\Doctrine;

use App\Context\Animal\Application\Port\WeightRecordReadRepositoryInterface;
use App\Context\Animal\Application\Query\ListAnimalWeightRecords\WeightRecordView;
use App\Context\Animal\Domain\ValueObject\AnimalId;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use App\Context\Animal\Infrastructure\Persistence\Doctrine\Entity\WeightRecordEntity;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Types\UuidType;
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

        return array_map(self::view(...), $entities);
    }

    public function listForAnimals(ClinicId $clinicId, array $animalIds): array
    {
        /** @var list<WeightRecordEntity> $entities */
        $entities = $this->em->createQueryBuilder()
            ->select('w')
            ->from(WeightRecordEntity::class, 'w')
            ->where('w.clinicId = :clinicId')
            ->andWhere('w.animalId IN (:ids)')
            ->orderBy('w.measuredOn', 'DESC')
            ->addOrderBy('w.recordedAt', 'DESC')
            ->addOrderBy('w.id', 'DESC')
            ->setParameter('clinicId', Uuid::fromString($clinicId->toString()), UuidType::NAME)
            ->setParameter('ids', array_map(static fn (string $id): string => Uuid::fromString($id)->toBinary(), $animalIds), ArrayParameterType::BINARY)
            ->getQuery()
            ->getResult()
        ;

        $records = [];

        foreach ($entities as $entity) {
            $records[$entity->animalId->toString()][] = self::view($entity);
        }

        return $records;
    }

    private static function view(WeightRecordEntity $entity): WeightRecordView
    {
        return new WeightRecordView(
            id: $entity->id->toString(),
            weightGrams: $entity->weightGrams,
            bodyConditionScore: $entity->bodyConditionScore,
            measuredOn: $entity->measuredOn,
            authorName: $entity->authorName,
        );
    }
}
