<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Schema of the owners' pet change requests; rows are written and read in
 * SQL by DbalPetChangeRequestRepository.
 */
#[ORM\Entity]
#[ORM\Table]
#[ORM\Index(name: 'idx_change_request_queue', columns: ['clinic_id', 'status', 'submitted_at'])]
#[ORM\Index(name: 'idx_change_request_pet_field', columns: ['clinic_id', 'pet_id', 'field', 'status'])]
#[ORM\Index(name: 'idx_change_request_client', columns: ['clinic_id', 'client_id', 'status'])]
#[ORM\Index(name: 'idx_change_request_user', columns: ['user_id', 'clinic_id', 'submitted_at'])]
class PetChangeRequestEntity
{
    /**
     * @param array<string, mixed> $normalisedValue
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
        #[ORM\Column(name: 'pet_id', type: UuidType::NAME)]
        public Uuid $petId,
        #[ORM\Column(type: 'string', length: 16)]
        public string $field,
        #[ORM\Column(name: 'proposed_value', type: 'string', length: 120)]
        public string $proposedValue,
        #[ORM\Column(name: 'normalised_value', type: 'json')]
        public array $normalisedValue,
        #[ORM\Column(name: 'current_value_at_submit', type: 'string', length: 120, nullable: true)]
        public ?string $currentValueAtSubmit,
        #[ORM\Column(type: 'string', length: 500, nullable: true)]
        public ?string $note,
        #[ORM\Column(type: 'string', length: 16)]
        public string $status,
        #[ORM\Column(name: 'submitted_at', type: 'datetime_immutable')]
        public \DateTimeImmutable $submittedAt,
        #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
        public \DateTimeImmutable $updatedAt,
        #[ORM\Column(name: 'resolved_at', type: 'datetime_immutable', nullable: true)]
        public ?\DateTimeImmutable $resolvedAt,
        #[ORM\Column(name: 'resolved_by_user_id', type: UuidType::NAME, nullable: true)]
        public ?Uuid $resolvedByUserId,
        #[ORM\Column(name: 'resolved_by_name', type: 'string', length: 255, nullable: true)]
        public ?string $resolvedByName,
        #[ORM\Column(name: 'resolution_message', type: 'string', length: 280, nullable: true)]
        public ?string $resolutionMessage,
        #[ORM\Column(name: 'notification_message_id', type: UuidType::NAME, nullable: true)]
        public ?Uuid $notificationMessageId,
        #[ORM\Column(name: 'client_unreachable', type: 'boolean')]
        public bool $clientUnreachable,
        #[ORM\Column(name: 'closed_at', type: 'datetime_immutable', nullable: true)]
        public ?\DateTimeImmutable $closedAt,
        #[ORM\Column(name: 'closed_reason', type: 'string', length: 32, nullable: true)]
        public ?string $closedReason,
    ) {
    }
}
