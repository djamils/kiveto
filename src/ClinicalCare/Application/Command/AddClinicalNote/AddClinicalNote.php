<?php

declare(strict_types=1);

namespace App\ClinicalCare\Application\Command\AddClinicalNote;

use App\Shared\Application\Bus\CommandInterface;

final readonly class AddClinicalNote implements CommandInterface
{
    public function __construct(
        public string $consultationId,
        public string $noteType,
        public string $content,
        public string $createdByUserId,
    ) {
    }
}
