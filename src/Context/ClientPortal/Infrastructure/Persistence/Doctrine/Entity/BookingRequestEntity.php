<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Schema of the new clients' booking requests (S7d); rows are written and
 * read in SQL by DbalBookingRequestRepository. The requester and new-pet
 * columns are nulled at account deletion.
 */
#[ORM\Entity]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uniq_booking_request_appointment', columns: ['appointment_id'])]
#[ORM\Index(name: 'idx_booking_request_user', columns: ['user_id', 'created_at', 'id'])]
#[ORM\Index(name: 'idx_booking_request_clinic_status', columns: ['clinic_id', 'status'])]
class BookingRequestEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME)]
        public Uuid $id,
        #[ORM\Column(name: 'user_id', type: UuidType::NAME)]
        public Uuid $userId,
        #[ORM\Column(name: 'clinic_id', type: UuidType::NAME)]
        public Uuid $clinicId,
        #[ORM\Column(name: 'listing_id', type: UuidType::NAME)]
        public Uuid $listingId,
        #[ORM\Column(name: 'appointment_id', type: UuidType::NAME)]
        public Uuid $appointmentId,
        #[ORM\Column(name: 'motif_label', type: 'string', length: 80)]
        public string $motifLabel,
        #[ORM\Column(name: 'start_at', type: 'datetime_immutable')]
        public \DateTimeImmutable $startAt,
        #[ORM\Column(name: 'new_pet_name', type: 'string', length: 80, nullable: true)]
        public ?string $newPetName,
        #[ORM\Column(name: 'new_pet_species', type: 'string', length: 8, nullable: true)]
        public ?string $newPetSpecies,
        #[ORM\Column(name: 'requester_first_name', type: 'string', length: 60, nullable: true)]
        public ?string $requesterFirstName,
        #[ORM\Column(name: 'requester_last_name', type: 'string', length: 60, nullable: true)]
        public ?string $requesterLastName,
        #[ORM\Column(name: 'requester_phone', type: 'string', length: 20, nullable: true)]
        public ?string $requesterPhone,
        #[ORM\Column(type: 'string', length: 10)]
        public string $status,
        #[ORM\Column(name: 'decline_reason', type: 'string', length: 16, nullable: true)]
        public ?string $declineReason,
        #[ORM\Column(name: 'client_id', type: UuidType::NAME, nullable: true)]
        public ?Uuid $clientId,
        #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
        public \DateTimeImmutable $createdAt,
        #[ORM\Column(name: 'resolved_at', type: 'datetime_immutable', nullable: true)]
        public ?\DateTimeImmutable $resolvedAt,
    ) {
    }
}
