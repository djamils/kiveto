<?php

declare(strict_types=1);

namespace App\Context\Clinic\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Clinic\Application\Port\ClinicReadRepositoryInterface;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\ListClinics\ClinicCollection;
use App\Context\Clinic\Domain\ValueObject\ClinicStatus;
use App\Context\Clinic\Infrastructure\Persistence\Doctrine\Entity\ClinicEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

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
    ): ClinicCollection {
        $qb = $this->em->getRepository(ClinicEntity::class)->createQueryBuilder('c');

        if (null !== $status) {
            $qb->andWhere('c.status = :status')
                ->setParameter('status', $status)
            ;
        }

        if (null !== $clinicGroupId) {
            $qb->andWhere('c.clinicGroupId = :clinicGroupId')
                ->setParameter('clinicGroupId', Uuid::fromString($clinicGroupId), UuidType::NAME)
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

        return new ClinicCollection(
            clinics: $dtos,
            total: \count($dtos),
        );
    }
}
