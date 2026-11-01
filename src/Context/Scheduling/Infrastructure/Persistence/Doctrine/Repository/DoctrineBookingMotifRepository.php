<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Scheduling\Domain\Booking\BookingMotif;
use App\Context\Scheduling\Domain\Booking\BookingMotifId;
use App\Context\Scheduling\Domain\Booking\BookingMotifRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Entity\BookingMotifEntity;
use App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Mapper\BookingMotifMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineBookingMotifRepository implements BookingMotifRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BookingMotifMapper $mapper,
    ) {
    }

    public function save(BookingMotif $motif): void
    {
        $entity = $this->entityManager->find(BookingMotifEntity::class, Uuid::fromString($motif->id()->toString()));

        if (null === $entity) {
            $this->entityManager->persist($this->mapper->toEntity($motif));
        } else {
            $this->mapper->updateEntity($motif, $entity);
        }

        $this->entityManager->flush();
    }

    public function findInClinic(BookingMotifId $id, ClinicId $clinicId): ?BookingMotif
    {
        $entity = $this->entityManager->getRepository(BookingMotifEntity::class)->findOneBy([
            'id'       => Uuid::fromString($id->toString()),
            'clinicId' => Uuid::fromString($clinicId->toString()),
        ]);

        return null === $entity ? null : $this->mapper->toDomain($entity);
    }

    public function listForClinic(ClinicId $clinicId, bool $includeArchived): array
    {
        $builder = $this->entityManager->createQueryBuilder()
            ->select('m', 'p')
            ->from(BookingMotifEntity::class, 'm')
            ->leftJoin('m.practitioners', 'p')
            ->where('m.clinicId = :clinic')
            ->setParameter('clinic', Uuid::fromString($clinicId->toString()), UuidType::NAME)
            ->orderBy('m.sortOrder', 'ASC')
            ->addOrderBy('m.label', 'ASC')
            ->addOrderBy('m.id', 'ASC')
        ;

        if (!$includeArchived) {
            $builder->andWhere('m.archivedAt IS NULL');
        }

        /** @var list<BookingMotifEntity> $entities */
        $entities = $builder->getQuery()->getResult();

        return array_map($this->mapper->toDomain(...), $entities);
    }
}
