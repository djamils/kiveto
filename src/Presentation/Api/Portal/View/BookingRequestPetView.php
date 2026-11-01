<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\View;

use ApiPlatform\Metadata\ApiProperty;

/** The new pet of a booking request; both null once the account data are erased. */
final readonly class BookingRequestPetView
{
    public function __construct(
        public ?string $name,
        #[ApiProperty(schema: ['type' => ['string', 'null'], 'enum' => ['dog', 'cat', 'nac', 'other', null]])]
        public ?string $species,
    ) {
    }
}
