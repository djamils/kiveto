<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Command\RecordExamSystem;

use App\Shared\Application\Bus\CommandInterface;

final readonly class RecordExamSystem implements CommandInterface
{
    /**
     * @param array<string, string> $structuredData
     */
    public function __construct(
        public string $consultationId,
        public string $clinicId,
        public string $system,
        public string $status,
        public ?string $notes,
        public array $structuredData,
        public string $recordedByUserId,
    ) {
    }
}
