<?php

declare(strict_types=1);

namespace App\Context\Animal\Infrastructure\Persistence\Doctrine;

use App\Context\Animal\Application\Port\PreventiveCareRecordReadRepositoryInterface;
use App\Context\Animal\Application\Query\ListAnimalPreventiveCare\PreventiveCareRecordView;
use App\Context\Animal\Domain\ValueObject\AnimalId;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use App\Context\Animal\Infrastructure\Persistence\Doctrine\Entity\PreventiveCareRecordEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrinePreventiveCareRecordReadRepository implements PreventiveCareRecordReadRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function listForAnimal(ClinicId $clinicId, AnimalId $animalId): array
    {
        $entities = $this->em->getRepository(PreventiveCareRecordEntity::class)->findBy(
            [
                'clinicId' => Uuid::fromString($clinicId->toString()),
                'animalId' => Uuid::fromString($animalId->toString()),
            ],
            ['administeredOn' => 'DESC', 'recordedAt' => 'DESC', 'id' => 'DESC'],
        );

        return array_map(
            static fn (PreventiveCareRecordEntity $entity): PreventiveCareRecordView => new PreventiveCareRecordView(
                id: $entity->id->toString(),
                kind: $entity->kind->value,
                label: $entity->label,
                administeredOn: $entity->administeredOn,
                batchNumber: $entity->batchNumber,
                nextDueOn: $entity->nextDueOn,
                authorName: $entity->authorName,
            ),
            $entities,
        );
    }
}
