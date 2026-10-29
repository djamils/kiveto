<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Schema of the owner profiles; rows are written and read in SQL by
 * DbalOwnerProfileRepository. The address columns are all null or line 1,
 * city and country set.
 */
#[ORM\Entity]
#[ORM\Table]
class OwnerProfileEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'user_id', type: UuidType::NAME)]
        public Uuid $userId,
        #[ORM\Column(name: 'first_name', type: 'string', length: 255)]
        public string $firstName,
        #[ORM\Column(name: 'last_name', type: 'string', length: 255)]
        public string $lastName,
        #[ORM\Column(name: 'address_line1', type: 'string', length: 255, nullable: true)]
        public ?string $addressLine1,
        #[ORM\Column(name: 'address_line2', type: 'string', length: 255, nullable: true)]
        public ?string $addressLine2,
        #[ORM\Column(name: 'address_postal_code', type: 'string', length: 20, nullable: true)]
        public ?string $addressPostalCode,
        #[ORM\Column(name: 'address_city', type: 'string', length: 255, nullable: true)]
        public ?string $addressCity,
        #[ORM\Column(name: 'address_country', type: 'string', length: 2, nullable: true, options: ['fixed' => true])]
        public ?string $addressCountry,
        #[ORM\Column(name: 'terms_version', type: 'string', length: 32)]
        public string $termsVersion,
        #[ORM\Column(name: 'terms_accepted_at', type: 'datetime_immutable')]
        public \DateTimeImmutable $termsAcceptedAt,
        #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
