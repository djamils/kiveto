<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Command\Article\UpdateArticlePrescriptionFlags;

use App\Shared\Application\Bus\CommandInterface;

/**
 * What a drug requires to be handed over. The pharmaceutical registry keeps
 * these flags in step on its own; this is the way a clinic sets them on a drug
 * the registry does not know, or corrects one it got wrong.
 */
final readonly class UpdateArticlePrescriptionFlags implements CommandInterface
{
    public function __construct(
        public string $articleId,
        public string $clinicId,
        public bool $requiresPrescription,
        public ?string $prescriptionClass,
        public bool $isControlledSubstance,
    ) {
    }
}
