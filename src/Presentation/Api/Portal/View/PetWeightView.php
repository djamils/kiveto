<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\View;

use ApiPlatform\Metadata\ApiProperty;

final readonly class PetWeightView
{
    public function __construct(
        public int $weightGrams,
        #[ApiProperty(schema: ['type' => 'string', 'format' => 'date'])]
        public string $at,
    ) {
    }
}
