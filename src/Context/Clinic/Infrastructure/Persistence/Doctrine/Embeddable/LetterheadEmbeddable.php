<?php

declare(strict_types=1);

namespace App\Context\Clinic\Infrastructure\Persistence\Doctrine\Embeddable;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final class LetterheadEmbeddable
{
    public function __construct(
        #[ORM\Column(name: 'letterhead_legal_name', type: 'string', length: 255, nullable: true)]
        public ?string $legalName = null,
        #[ORM\Column(name: 'letterhead_legal_form', type: 'string', length: 120, nullable: true)]
        public ?string $legalForm = null,
        #[ORM\Column(name: 'letterhead_share_capital', type: 'string', length: 60, nullable: true)]
        public ?string $shareCapital = null,
        #[ORM\Column(name: 'letterhead_siret', type: 'string', length: 14, nullable: true)]
        public ?string $siret = null,
        #[ORM\Column(name: 'letterhead_vat_number', type: 'string', length: 20, nullable: true)]
        public ?string $vatNumber = null,
        #[ORM\Column(name: 'letterhead_rcs_city', type: 'string', length: 120, nullable: true)]
        public ?string $rcsCity = null,
        #[ORM\Column(name: 'letterhead_rcs_number', type: 'string', length: 40, nullable: true)]
        public ?string $rcsNumber = null,
        #[ORM\Column(name: 'letterhead_ape_code', type: 'string', length: 10, nullable: true)]
        public ?string $apeCode = null,
        #[ORM\Column(name: 'letterhead_ordinal_number', type: 'string', length: 40, nullable: true)]
        public ?string $ordinalNumber = null,
        #[ORM\Column(name: 'letterhead_street_line_1', type: 'string', length: 255, nullable: true)]
        public ?string $streetLine1 = null,
        #[ORM\Column(name: 'letterhead_street_line_2', type: 'string', length: 255, nullable: true)]
        public ?string $streetLine2 = null,
        #[ORM\Column(name: 'letterhead_postal_code', type: 'string', length: 20, nullable: true)]
        public ?string $postalCode = null,
        #[ORM\Column(name: 'letterhead_city', type: 'string', length: 255, nullable: true)]
        public ?string $city = null,
        #[ORM\Column(name: 'letterhead_country_code', type: 'string', length: 2, nullable: true)]
        public ?string $countryCode = null,
        #[ORM\Column(name: 'letterhead_phone', type: 'string', length: 20, nullable: true)]
        public ?string $phone = null,
        #[ORM\Column(name: 'letterhead_email', type: 'string', length: 255, nullable: true)]
        public ?string $email = null,
        #[ORM\Column(name: 'letterhead_website', type: 'string', length: 255, nullable: true)]
        public ?string $website = null,
        #[ORM\Column(name: 'letterhead_ordinal_council', type: 'string', length: 8, nullable: true)]
        public ?string $ordinalCouncil = null,
        #[ORM\Column(name: 'letterhead_liability_insurer', type: 'string', length: 80, nullable: true)]
        public ?string $liabilityInsurer = null,
        #[ORM\Column(name: 'letterhead_liability_policy_number', type: 'string', length: 40, nullable: true)]
        public ?string $liabilityPolicyNumber = null,
    ) {
    }
}
