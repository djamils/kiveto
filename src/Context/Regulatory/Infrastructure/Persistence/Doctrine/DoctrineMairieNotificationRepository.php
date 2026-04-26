<?php

declare(strict_types=1);

namespace App\Context\Regulatory\Infrastructure\Persistence\Doctrine;

use App\Context\Regulatory\Domain\MairieNotification;
use App\Context\Regulatory\Domain\Repository\MairieNotificationRepositoryInterface;
use App\Context\Regulatory\Infrastructure\Persistence\Doctrine\Entity\MairieNotificationEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineMairieNotificationRepository implements MairieNotificationRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MairieNotificationMapper $mapper,
    ) {
    }

    public function save(MairieNotification $notification): void
    {
        $uuid       = Uuid::fromString($notification->id()->value());
        $repository = $this->entityManager->getRepository(MairieNotificationEntity::class);
        $entity     = $repository->find($uuid);

        if (null === $entity) {
            $entity = $this->mapper->toEntity($notification);
            $this->entityManager->persist($entity);
        } else {
            $entity->setStatus($notification->status());
            $entity->setUpdatedAt($notification->updatedAt());
        }

        $this->entityManager->flush();
    }

    public function findByAdmissionId(string $admissionId): ?MairieNotification
    {
        $admissionUuid = Uuid::fromString($admissionId);
        $repository    = $this->entityManager->getRepository(MairieNotificationEntity::class);

        $entity = $repository->findOneBy(['admissionId' => $admissionUuid]);

        if (null === $entity) {
            return null;
        }

        return $this->mapper->toDomain($entity);
    }
}
