<?php

declare(strict_types=1);

namespace App\Clinic\Infrastructure\Persistence\Doctrine\Repository;

use App\Clinic\Application\Port\ClinicReadRepositoryInterface;
use App\Clinic\Application\Query\GetClinic\ClinicDto;
use App\Clinic\Application\Query\ListClinics\ClinicsCollection;
use App\Clinic\Domain\ValueObject\ClinicStatus;
use App\Clinic\Infrastructure\Persistence\Doctrine\Entity\ClinicEntity;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineClinicReadRepository implements ClinicReadRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function findAllFiltered(
        ?ClinicStatus $status = null,
        ?string $clinicGroupId = null,
        ?string $search = null,
    ): ClinicsCollection {
        $qb = $this->em->getRepository(ClinicEntity::class)->createQueryBuilder('c');

        if (null !== $status) {
            $qb->andWhere('c.status = :status')
                ->setParameter('status', $status)
            ;
        }

        if (null !== $clinicGroupId) {
            $qb->andWhere('c.clinicGroupId = :clinicGroupId')
                ->setParameter('clinicGroupId', $clinicGroupId)
            ;
        }

        if (null !== $search && '' !== trim($search)) {
            $qb->andWhere('c.name LIKE :search OR c.slug LIKE :search')
                ->setParameter('search', '%' . $search . '%')
            ;
        }

        $qb->orderBy('c.name', 'ASC');

        /** @var list<ClinicEntity> $entities */
        $entities = $qb->getQuery()->getResult();

        $dtos = array_map(
            static fn (ClinicEntity $entity): ClinicDto => new ClinicDto(
                id: $entity->getId()->toString(),
                name: $entity->getName(),
                slug: $entity->getSlug(),
                timeZone: $entity->getTimeZone(),
                locale: $entity->getLocale(),
                status: $entity->getStatus(),
                clinicGroupId: $entity->getClinicGroupId()?->toString(),
                createdAt: $entity->getCreatedAt()->format('c'),
                updatedAt: $entity->getUpdatedAt()->format('c'),
            ),
            $entities,
        );

        return new ClinicsCollection(
            clinics: $dtos,
            total: \count($dtos),
        );
    }
}
