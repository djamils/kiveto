<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Command\RemoveDiagnosis;

use App\Shared\Application\Bus\CommandInterface;

final readonly class RemoveDiagnosis implements CommandInterface
{
    public function __construct(
        public string $consultationId,
        public string $clinicId,
        public string $diagnosisId,
    ) {
    }
}
