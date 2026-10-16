<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Port;

/**
 * A medicine of the catalogue that is only dispensed on a prescription.
 */
final readonly class PrescriptionOnlyArticleDto
{
    public function __construct(
        public string $articleId,
        public string $label,
    ) {
    }
}
