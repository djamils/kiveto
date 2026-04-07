<?php

declare(strict_types=1);

namespace App\Scheduling\Infrastructure\Persistence\Doctrine\Repository;

use App\Scheduling\Domain\Appointment;
use App\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Scheduling\Domain\ValueObject\AppointmentId;
use App\Scheduling\Infrastructure\Persistence\Doctrine\Entity\AppointmentEntity;
use App\Scheduling\Infrastructure\Persistence\Doctrine\Mapper\AppointmentMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineAppointmentRepository implements AppointmentRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AppointmentMapper $mapper,
    ) {
    }

    public function save(Appointment $appointment): void
    {
        $repository = $this->entityManager->getRepository(AppointmentEntity::class);
        $entity     = $repository->find(Uuid::fromString($appointment->id()->toString()));

        if (null === $entity) {
            $entity = $this->mapper->toEntity($appointment);
            $this->entityManager->persist($entity);
        } else {
            $this->mapper->updateEntity($appointment, $entity);
        }

        $this->entityManager->flush();
    }

    public function findById(AppointmentId $id): ?Appointment
    {
        $repository = $this->entityManager->getRepository(AppointmentEntity::class);
        $entity     = $repository->find(Uuid::fromString($id->toString()));

        if (null === $entity) {
            return null;
        }

        return $this->mapper->toDomain($entity);
    }
}
