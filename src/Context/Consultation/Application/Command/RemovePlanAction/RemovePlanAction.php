<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Command\RemovePlanAction;

use App\Shared\Application\Bus\CommandInterface;

final readonly class RemovePlanAction implements CommandInterface
{
    public function __construct(
        public string $consultationId,
        public string $clinicId,
        public string $planActionId,
    ) {
    }
}
