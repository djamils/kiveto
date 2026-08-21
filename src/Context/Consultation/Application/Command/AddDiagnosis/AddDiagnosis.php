<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Command\AddDiagnosis;

use App\Shared\Application\Bus\CommandInterface;

final readonly class AddDiagnosis implements CommandInterface
{
    public function __construct(
        public string $consultationId,
        public string $clinicId,
        public ?string $code,
        public string $label,
        public string $certainty,
        public ?string $note,
        public bool $isPrimary,
        public string $source,
        public string $createdByUserId,
    ) {
    }
}
