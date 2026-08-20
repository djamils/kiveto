<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Command\MarkAllSystemsNormal;

use App\Shared\Application\Bus\CommandInterface;

final readonly class MarkAllSystemsNormal implements CommandInterface
{
    /**
     * @param list<string> $systems
     */
    public function __construct(
        public string $consultationId,
        public string $clinicId,
        public array $systems,
        public string $recordedByUserId,
    ) {
    }
}
