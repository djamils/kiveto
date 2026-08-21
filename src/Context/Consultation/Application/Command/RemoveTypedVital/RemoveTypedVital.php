<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Command\RemoveTypedVital;

use App\Shared\Application\Bus\CommandInterface;

final readonly class RemoveTypedVital implements CommandInterface
{
    public function __construct(
        public string $consultationId,
        public string $clinicId,
        public string $type,
    ) {
    }
}
