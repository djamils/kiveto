<?php

declare(strict_types=1);

namespace App\Context\Clinic\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Clinic\Application\Port\ClinicGroupReadRepositoryInterface;
use App\Context\Clinic\Application\Query\GetClinicGroup\ClinicGroupDto;
use App\Context\Clinic\Application\Query\ListClinicGroups\ClinicGroupCollection;
use App\Context\Clinic\Domain\ValueObject\ClinicGroupStatus;
use App\Context\Clinic\Infrastructure\Persistence\Doctrine\Entity\ClinicGroupEntity;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineClinicGroupReadRepository implements ClinicGroupReadRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function findAllFiltered(?ClinicGroupStatus $status = null): ClinicGroupCollection
    {
        $qb = $this->em->getRepository(ClinicGroupEntity::class)->createQueryBuilder('cg');

        if (null !== $status) {
            $qb->andWhere('cg.status = :status')
                ->setParameter('status', $status)
            ;
        }

        $qb->orderBy('cg.name', 'ASC');

        /** @var list<ClinicGroupEntity> $entities */
        $entities = $qb->getQuery()->getResult();

        $dtos = array_map(
            static fn (ClinicGroupEntity $entity): ClinicGroupDto => new ClinicGroupDto(
                id: $entity->getId()->toString(),
                name: $entity->getName(),
                status: $entity->getStatus(),
                createdAt: $entity->getCreatedAt()->format('c'),
            ),
            $entities,
        );

        return new ClinicGroupCollection(
            clinicGroups: $dtos,
            total: \count($dtos),
        );
    }
}
