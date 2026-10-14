<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Command\AddConsultationAddendum;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Signs a dated addendum onto a closed consultation.
 */
final readonly class AddConsultationAddendum implements CommandInterface
{
    public function __construct(
        public string $consultationId,
        public string $clinicId,
        public string $content,
        public string $createdByUserId,
    ) {
    }
}
