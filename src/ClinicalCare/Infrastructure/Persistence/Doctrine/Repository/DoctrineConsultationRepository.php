<?php

declare(strict_types=1);

namespace App\ClinicalCare\Infrastructure\Persistence\Doctrine\Repository;

use App\ClinicalCare\Domain\Consultation;
use App\ClinicalCare\Domain\Repository\ConsultationRepositoryInterface;
use App\ClinicalCare\Domain\ValueObject\ConsultationId;
use App\ClinicalCare\Infrastructure\Persistence\Doctrine\Entity\ClinicalNoteEntity;
use App\ClinicalCare\Infrastructure\Persistence\Doctrine\Entity\ConsultationEntity;
use App\ClinicalCare\Infrastructure\Persistence\Doctrine\Entity\PerformedActEntity;
use App\ClinicalCare\Infrastructure\Persistence\Doctrine\Mapper\ClinicalNoteMapper;
use App\ClinicalCare\Infrastructure\Persistence\Doctrine\Mapper\ConsultationMapper;
use App\ClinicalCare\Infrastructure\Persistence\Doctrine\Mapper\PerformedActMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineConsultationRepository implements ConsultationRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private ConsultationMapper $mapper,
        private ClinicalNoteMapper $noteMapper,
        private PerformedActMapper $actMapper,
    ) {
    }

    public function save(Consultation $consultation): void
    {
        $consultationEntity = $this->mapper->toEntity($consultation);
        $this->em->persist($consultationEntity);

        // Persist notes (delete all + re-insert for simplicity)
        $consultationIdBinary = $consultationEntity->getId();

        // Delete existing notes & acts
        $this->em->createQueryBuilder()
            ->delete(ClinicalNoteEntity::class, 'n')
            ->where('n.consultationId = :consultationId')
            ->setParameter('consultationId', $consultationIdBinary)
            ->getQuery()
            ->execute()
        ;

        $this->em->createQueryBuilder()
            ->delete(PerformedActEntity::class, 'a')
            ->where('a.consultationId = :consultationId')
            ->setParameter('consultationId', $consultationIdBinary)
            ->getQuery()
            ->execute()
        ;

        // Insert notes
        foreach ($consultation->getNotes() as $note) {
            $noteEntity = $this->noteMapper->toEntity($note, $consultationIdBinary);
            $this->em->persist($noteEntity);
        }

        // Insert acts
        foreach ($consultation->getActs() as $act) {
            $actEntity = $this->actMapper->toEntity($act, $consultationIdBinary);
            $this->em->persist($actEntity);
        }

        $this->em->flush();
    }

    public function findById(ConsultationId $id): ?Consultation
    {
        $consultationIdBinary = Uuid::fromString($id->toString())->toBinary();

        /** @var ConsultationEntity|null $entity */
        $entity = $this->em->find(ConsultationEntity::class, $consultationIdBinary);

        if (null === $entity) {
            return null;
        }

        // Load notes
        $noteEntities = $this->em->getRepository(ClinicalNoteEntity::class)
            ->findBy(['consultationId' => $consultationIdBinary], ['createdAtUtc' => 'ASC'])
        ;

        // Load acts
        $actEntities = $this->em->getRepository(PerformedActEntity::class)
            ->findBy(['consultationId' => $consultationIdBinary], ['performedAtUtc' => 'ASC'])
        ;

        return $this->mapper->toDomain($entity, $noteEntities, $actEntities);
    }
}
