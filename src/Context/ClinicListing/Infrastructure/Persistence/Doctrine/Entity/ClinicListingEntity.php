<?php

declare(strict_types=1);

namespace App\Context\ClinicListing\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Schema of the directory projection, one row per clinic (never deleted);
 * rows are written and read in SQL by DbalClinicListingRepository and
 * DbalClinicListingReadModel. `id` is the public listing id, distinct from
 * `clinic_id`.
 */
#[ORM\Entity]
#[ORM\Table(name: 'clinic_listing__clinic_listings')]
#[ORM\UniqueConstraint(name: 'uniq_clinic_listing_id', columns: ['id'])]
#[ORM\Index(name: 'idx_clinic_listing_active_city', columns: ['active', 'city'])]
#[ORM\Index(name: 'idx_clinic_listing_lat', columns: ['lat'])]
#[ORM\Index(name: 'idx_clinic_listing_lng', columns: ['lng'])]
class ClinicListingEntity
{
    /**
     * @param list<array{day: int, opensAt: string, closesAt: string}> $openingHours
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'clinic_id', type: UuidType::NAME)]
        public Uuid $clinicId,
        #[ORM\Column(name: 'id', type: UuidType::NAME)]
        public Uuid $id,
        #[ORM\Column(name: 'name', type: 'string', length: 255)]
        public string $name,
        #[ORM\Column(name: 'address_line', type: 'string', length: 255, nullable: true)]
        public ?string $addressLine,
        #[ORM\Column(name: 'postal_code', type: 'string', length: 20, nullable: true)]
        public ?string $postalCode,
        #[ORM\Column(name: 'city', type: 'string', length: 255, nullable: true)]
        public ?string $city,
        #[ORM\Column(name: 'phone', type: 'string', length: 32, nullable: true)]
        public ?string $phone,
        #[ORM\Column(name: 'lat', type: 'decimal', precision: 8, scale: 6, nullable: true)]
        public ?string $lat,
        #[ORM\Column(name: 'lng', type: 'decimal', precision: 9, scale: 6, nullable: true)]
        public ?string $lng,
        #[ORM\Column(name: 'address_hash', type: 'string', length: 64, nullable: true, options: ['fixed' => true])]
        public ?string $addressHash,
        #[ORM\Column(name: 'timezone', type: 'string', length: 64)]
        public string $timezone,
        #[ORM\Column(name: 'opening_hours', type: 'json')]
        public array $openingHours,
        #[ORM\Column(name: 'active', type: 'boolean')]
        public bool $active,
        #[ORM\Column(name: 'refreshed_at', type: 'datetime_immutable')]
        public \DateTimeImmutable $refreshedAt,
    ) {
    }
}
