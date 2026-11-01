<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * A practitioner who takes a booking motif.
 */
#[ORM\Entity]
#[ORM\Table(name: 'scheduling__booking_motif_practitioners')]
#[ORM\Index(name: 'idx_sched_motif_practitioner_user', columns: ['user_id'])]
class BookingMotifPractitionerEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\ManyToOne(targetEntity: BookingMotifEntity::class, inversedBy: 'practitioners')]
        #[ORM\JoinColumn(name: 'motif_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
        private BookingMotifEntity $motif,
        #[ORM\Id]
        #[ORM\Column(name: 'user_id', type: UuidType::NAME)]
        private Uuid $userId,
    ) {
    }

    public function getMotif(): BookingMotifEntity
    {
        return $this->motif;
    }

    public function getUserId(): Uuid
    {
        return $this->userId;
    }
}
