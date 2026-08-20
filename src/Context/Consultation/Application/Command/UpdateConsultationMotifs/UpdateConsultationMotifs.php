<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Command\UpdateConsultationMotifs;

use App\Shared\Application\Bus\CommandInterface;

final readonly class UpdateConsultationMotifs implements CommandInterface
{
    /**
     * @param list<string> $labels
     */
    public function __construct(
        public string $consultationId,
        public string $clinicId,
        public array $labels,
    ) {
    }
}
