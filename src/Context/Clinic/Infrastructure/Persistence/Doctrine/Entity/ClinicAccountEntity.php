<?php

declare(strict_types=1);

namespace App\Context\Clinic\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Schema of the clinics' accounts (the console's side of a clinic); rows
 * are written and read in SQL by DbalClinicAccountRepository.
 */
#[ORM\Entity]
#[ORM\Table]
class ClinicAccountEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'clinic_id', type: UuidType::NAME)]
        public Uuid $clinicId,
        #[ORM\Column(type: 'string', length: 16)]
        public string $plan,
        #[ORM\Column(name: 'pilot_ends_on', type: 'date_immutable', nullable: true)]
        public ?\DateTimeImmutable $pilotEndsOn,
        #[ORM\Column(name: 'parallel_software', type: 'string', length: 16, nullable: true)]
        public ?string $parallelSoftware,
        #[ORM\Column(name: 'internal_note', type: 'text')]
        public string $internalNote,
        #[ORM\Column(name: 'onboarded_at', type: 'datetime_immutable', nullable: true)]
        public ?\DateTimeImmutable $onboardedAt,
        #[ORM\Column(name: 'suspension_reason', type: 'string', length: 24, nullable: true)]
        public ?string $suspensionReason,
        #[ORM\Column(name: 'suspension_comment', type: 'string', length: 500, nullable: true)]
        public ?string $suspensionComment,
        #[ORM\Column(name: 'suspended_at', type: 'datetime_immutable', nullable: true)]
        public ?\DateTimeImmutable $suspendedAt,
        #[ORM\Column(name: 'suspended_by_id', type: UuidType::NAME, nullable: true)]
        public ?Uuid $suspendedById,
        #[ORM\Column(name: 'suspended_by_name', type: 'string', length: 160, nullable: true)]
        public ?string $suspendedByName,
        #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
        public \DateTimeImmutable $createdAt,
        #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
        public \DateTimeImmutable $updatedAt,
    ) {
    }
}
