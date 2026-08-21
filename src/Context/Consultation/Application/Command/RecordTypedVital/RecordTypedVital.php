<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Command\RecordTypedVital;

use App\Shared\Application\Bus\CommandInterface;

final readonly class RecordTypedVital implements CommandInterface
{
    public function __construct(
        public string $consultationId,
        public string $clinicId,
        public string $type,
        public string $value,
        public string $recordedByUserId,
    ) {
    }
}
