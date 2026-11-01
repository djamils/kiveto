<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Mutex rows of the practitioner-day lock, written in SQL by
 * DbalPractitionerDayLock (upsert, purged monthly).
 */
#[ORM\Entity]
#[ORM\Table(name: 'scheduling__practitioner_day_locks')]
#[ORM\Index(name: 'idx_sched_day_lock_date', columns: ['local_date'])]
class PractitionerDayLockEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'clinic_id', type: UuidType::NAME)]
        private Uuid $clinicId,
        #[ORM\Id]
        #[ORM\Column(name: 'practitioner_user_id', type: UuidType::NAME)]
        private Uuid $practitionerUserId,
        #[ORM\Id]
        #[ORM\Column(name: 'local_date', type: 'date_immutable')]
        private \DateTimeImmutable $localDate,
    ) {
    }

    /**
     * @return array{clinic_id: Uuid, practitioner_user_id: Uuid, local_date: \DateTimeImmutable}
     */
    public function columns(): array
    {
        return ['clinic_id' => $this->clinicId, 'practitioner_user_id' => $this->practitionerUserId, 'local_date' => $this->localDate];
    }
}
