<?php

declare(strict_types=1);

namespace App\Clinic\Infrastructure\Persistence\Doctrine\Repository;

use App\Clinic\Application\Port\ClinicGroupReadRepositoryInterface;
use App\Clinic\Application\Query\GetClinicGroup\ClinicGroupDto;
use App\Clinic\Application\Query\ListClinicGroups\ClinicGroupsCollection;
use App\Clinic\Domain\ValueObject\ClinicGroupStatus;
use App\Clinic\Infrastructure\Persistence\Doctrine\Entity\ClinicGroupEntity;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineClinicGroupReadRepository implements ClinicGroupReadRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function findAllFiltered(?ClinicGroupStatus $status = null): ClinicGroupsCollection
    {
        $qb = $this->em->getRepository(ClinicGroupEntity::class)->createQueryBuilder('cg');

        if (null !== $status) {
            $qb->andWhere('cg.status = :status')
                ->setParameter('status', $status);
        }

        $qb->orderBy('cg.name', 'ASC');

        $entities = $qb->getQuery()->getResult();

        $dtos = \array_map(
            fn (ClinicGroupEntity $entity) => new ClinicGroupDto(
                id: $entity->getId()->toString(),
                name: $entity->getName(),
                status: $entity->getStatus(),
                createdAt: $entity->getCreatedAt()->format('c'),
            ),
            $entities,
        );

        return new ClinicGroupsCollection(
            clinicGroups: $dtos,
            total: \count($dtos),
        );
    }
}
