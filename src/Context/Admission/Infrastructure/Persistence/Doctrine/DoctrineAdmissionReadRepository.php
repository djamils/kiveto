<?php

declare(strict_types=1);

namespace App\Context\Admission\Infrastructure\Persistence\Doctrine;

use App\Context\Admission\Application\Port\AdmissionContextDto;
use App\Context\Admission\Application\Port\AdmissionReadRepositoryInterface;
use App\Context\Admission\Application\Port\WaitingRoomItemDto;
use App\Context\Admission\Domain\ValueObject\AdmissionStatus;
use App\Context\Admission\Domain\ValueObject\LocationStatusValue;
use App\Context\Admission\Infrastructure\Persistence\Doctrine\Entity\AdmissionEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineAdmissionReadRepository implements AdmissionReadRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<WaitingRoomItemDto>
     */
    public function findActiveInWaitingRoom(string $clinicId): array
    {
        $clinicUuid = Uuid::fromString($clinicId);

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('a')
            ->from(AdmissionEntity::class, 'a')
            ->where('a.clinicId = :clinicId')
            ->andWhere('a.status = :status')
            ->andWhere('a.locationStatusValue = :locationStatus')
            ->setParameter('clinicId', $clinicUuid, UuidType::NAME)
            ->setParameter('status', AdmissionStatus::Active)
            ->setParameter('locationStatus', LocationStatusValue::InWaitingRoom)
            ->orderBy('a.openedAt', 'ASC')
        ;

        /** @var list<AdmissionEntity> $entities */
        $entities = $qb->getQuery()->getResult();

        $items = [];
        foreach ($entities as $entity) {
            $items[] = new WaitingRoomItemDto(
                admissionId: $entity->getId()->toString(),
                clinicId: $entity->getClinicId()->toString(),
                patientId: $entity->getPatientId()->toString(),
                displayLabel: $entity->getPatientId()->toString(),
                triageLevel: $entity->getTriageLevel()->value,
                locationStatus: $entity->getLocationStatusValue()->value,
                intakeChannel: $entity->getIntakeChannel()->value,
                openedAt: $entity->getOpenedAt()->format(\DateTimeInterface::ATOM),
                triageNotes: $entity->getTriageNotes(),
            );
        }

        return $items;
    }

    public function getAdmissionContext(string $admissionId): AdmissionContextDto
    {
        $admissionUuid = Uuid::fromString($admissionId);
        $repository    = $this->entityManager->getRepository(AdmissionEntity::class);

        /** @var AdmissionEntity|null $entity */
        $entity = $repository->find($admissionUuid);

        if (null === $entity) {
            throw new \RuntimeException(\sprintf('Admission not found: %s', $admissionId));
        }

        return new AdmissionContextDto(
            patientId: $entity->getPatientId()->toString(),
            clinicId: $entity->getClinicId()->toString(),
        );
    }
}
