<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Input;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Validator\Constraints as Assert;

/** The postal address of `PATCH /me`, stored as typed (no geocoding). */
final class ProfileAddress
{
    #[Assert\NotBlank(message: 'L\'adresse est obligatoire.', normalizer: 'trim')]
    #[Assert\Length(max: 255, maxMessage: 'L\'adresse comporte {{ limit }} caractères au plus.')]
    public string $streetLine1;

    #[Assert\Length(max: 20, maxMessage: 'Le code postal comporte {{ limit }} caractères au plus.')]
    public ?string $postalCode = null;

    #[Assert\NotBlank(message: 'La ville est obligatoire.', normalizer: 'trim')]
    #[Assert\Length(max: 255, maxMessage: 'La ville comporte {{ limit }} caractères au plus.')]
    public string $city;

    /** ISO 3166-1 alpha-2. */
    #[ApiProperty(schema: ['type' => 'string', 'pattern' => '^[A-Z]{2}$', 'example' => 'FR'])]
    #[Assert\NotBlank(message: 'Le pays est obligatoire.')]
    #[Assert\Country(message: 'Pays invalide : code ISO à deux lettres attendu (« FR »).')]
    public string $countryCode;
}
