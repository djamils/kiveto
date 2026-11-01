<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Schema of the pre-visit questionnaires (one per appointment); rows are
 * written and read in SQL by DbalPreVisitQuestionnaireRepository.
 */
#[ORM\Entity]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uniq_questionnaire_appointment', columns: ['clinic_id', 'appointment_id'])]
#[ORM\Index(name: 'idx_questionnaire_client', columns: ['clinic_id', 'client_id', 'status'])]
#[ORM\Index(name: 'idx_questionnaire_user', columns: ['user_id'])]
class PreVisitQuestionnaireEntity
{
    /**
     * @param array<string, mixed> $answers
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME)]
        public Uuid $id,
        #[ORM\Column(name: 'clinic_id', type: UuidType::NAME)]
        public Uuid $clinicId,
        #[ORM\Column(name: 'client_id', type: UuidType::NAME)]
        public Uuid $clientId,
        #[ORM\Column(name: 'user_id', type: UuidType::NAME)]
        public Uuid $userId,
        #[ORM\Column(name: 'appointment_id', type: UuidType::NAME)]
        public Uuid $appointmentId,
        #[ORM\Column(type: 'smallint')]
        public int $version,
        #[ORM\Column(type: 'json')]
        public array $answers,
        #[ORM\Column(type: 'string', length: 16)]
        public string $status,
        #[ORM\Column(name: 'submitted_at', type: 'datetime_immutable')]
        public \DateTimeImmutable $submittedAt,
        #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
        public \DateTimeImmutable $updatedAt,
        #[ORM\Column(name: 'locked_at', type: 'datetime_immutable', nullable: true)]
        public ?\DateTimeImmutable $lockedAt,
        #[ORM\Column(name: 'closed_at', type: 'datetime_immutable', nullable: true)]
        public ?\DateTimeImmutable $closedAt,
        #[ORM\Column(name: 'closed_reason', type: 'string', length: 32, nullable: true)]
        public ?string $closedReason,
    ) {
    }
}
