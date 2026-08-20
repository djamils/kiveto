<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Command\UpdateDiagnosis;

use App\Shared\Application\Bus\CommandInterface;

final readonly class UpdateDiagnosis implements CommandInterface
{
    public function __construct(
        public string $consultationId,
        public string $clinicId,
        public string $diagnosisId,
        public ?string $code,
        public string $label,
        public string $certainty,
        public ?string $note,
    ) {
    }
}
