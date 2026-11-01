<?php

declare(strict_types=1);

namespace App\Context\ClinicListing\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Schema of the directory settings of the clinics (VetApp « Annuaire »);
 * rows are written and read in SQL by DbalListingSettingsRepository. No row
 * means the defaults (not listed).
 */
#[ORM\Entity]
#[ORM\Table(name: 'clinic_listing__listing_settings')]
class ListingSettingsEntity
{
    /**
     * @param list<string> $tags
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'clinic_id', type: UuidType::NAME)]
        public Uuid $clinicId,
        #[ORM\Column(name: 'listed', type: 'boolean')]
        public bool $listed,
        #[ORM\Column(name: 'accepts_new_clients', type: 'boolean')]
        public bool $acceptsNewClients,
        #[ORM\Column(name: 'tags', type: 'json')]
        public array $tags,
    ) {
    }
}
