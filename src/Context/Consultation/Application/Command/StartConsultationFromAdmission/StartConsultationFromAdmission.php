<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Command\StartConsultationFromAdmission;

use App\Shared\Application\Bus\CommandInterface;

final readonly class StartConsultationFromAdmission implements CommandInterface
{
    public function __construct(
        public string $admissionId,
        public string $startedByUserId,
    ) {
    }
}
