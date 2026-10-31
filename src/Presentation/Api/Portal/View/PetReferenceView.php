<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\View;

final readonly class PetReferenceView
{
    public function __construct(
        public ?string $id,
        public string $name,
    ) {
    }
}
