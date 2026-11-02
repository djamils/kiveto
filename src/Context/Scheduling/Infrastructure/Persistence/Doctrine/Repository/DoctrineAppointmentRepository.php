<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Scheduling\Domain\Appointment;
use App\Context\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Entity\AppointmentEntity;
use App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Mapper\AppointmentMapper;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Symfony\Bridge\Doctrine\Types\UuidType;
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

    public function findByIdForUpdate(AppointmentId $id): ?Appointment
    {
        // Refreshed: an entity already loaded by a plain read of this transaction is read again, current
        $entity = $this->entityManager->createQueryBuilder()
            ->select('a')
            ->from(AppointmentEntity::class, 'a')
            ->where('a.id = :id')
            ->setParameter('id', Uuid::fromString($id->toString()), UuidType::NAME)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->setHint(Query::HINT_REFRESH, true)
            ->getOneOrNullResult()
        ;

        return $entity instanceof AppointmentEntity ? $this->mapper->toDomain($entity) : null;
    }

    public function expiredPendingIds(\DateTimeImmutable $now): array
    {
        $ids = $this->entityManager->getConnection()->fetchFirstColumn(
            'SELECT id FROM scheduling__appointments WHERE status = :pending AND pending_expires_at <= :now ORDER BY pending_expires_at, id',
            [
                'pending' => AppointmentStatus::PENDING_CLINIC_CONFIRMATION->value,
                'now'     => $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            ],
        );

        return array_map(static fn (mixed $id): string => Uuid::fromBinary(\is_string($id) ? $id : '')->toRfc4122(), $ids);
    }

    public function findRecentOnlineBookingId(
        ClinicId $clinicId,
        string $ownerId,
        string $animalId,
        string $bookingMotifId,
        string $practitionerUserId,
        \DateTimeImmutable $startsAtUtc,
        \DateTimeImmutable $createdSince,
    ): ?AppointmentId {
        $utc = new \DateTimeZone('UTC');
        $id  = $this->entityManager->getConnection()->fetchOne(
            'SELECT id FROM scheduling__appointments
             WHERE clinic_id = :clinicId
               AND owner_id = :ownerId
               AND animal_id = :animalId
               AND booking_motif_id = :motifId
               AND practitioner_user_id = :practitionerId
               AND starts_at_utc = :startsAt
               AND created_at_utc >= :since
               AND status IN (:occupying)
             ORDER BY created_at_utc, id
             LIMIT 1
             FOR SHARE',
            [
                'clinicId'       => Uuid::fromString($clinicId->toString())->toBinary(),
                'ownerId'        => Uuid::fromString($ownerId)->toBinary(),
                'animalId'       => Uuid::fromString($animalId)->toBinary(),
                'motifId'        => Uuid::fromString($bookingMotifId)->toBinary(),
                'practitionerId' => Uuid::fromString($practitionerUserId)->toBinary(),
                'startsAt'       => $startsAtUtc->setTimezone($utc)->format('Y-m-d H:i:s'),
                'since'          => $createdSince->setTimezone($utc)->format('Y-m-d H:i:s'),
                'occupying'      => AppointmentStatus::OCCUPYING_SQL_VALUES,
            ],
            ['occupying' => ArrayParameterType::STRING],
        );

        return \is_string($id) ? AppointmentId::fromString(Uuid::fromBinary($id)->toRfc4122()) : null;
    }

    public function hasOtherAppointmentForAnimal(ClinicId $clinicId, string $animalId, AppointmentId $except): bool
    {
        $found = $this->entityManager->getConnection()->fetchOne(
            'SELECT 1 FROM scheduling__appointments
             WHERE clinic_id = :clinicId AND animal_id = :animalId AND id != :except AND status NOT IN (:withdrawn)
             LIMIT 1',
            [
                'clinicId'  => Uuid::fromString($clinicId->toString())->toBinary(),
                'animalId'  => Uuid::fromString($animalId)->toBinary(),
                'except'    => Uuid::fromString($except->toString())->toBinary(),
                'withdrawn' => [AppointmentStatus::CANCELLED->value, AppointmentStatus::DECLINED->value],
            ],
            ['withdrawn' => ArrayParameterType::STRING],
        );

        return false !== $found;
    }
}
