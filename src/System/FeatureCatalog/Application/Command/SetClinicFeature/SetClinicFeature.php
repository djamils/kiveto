<?php

declare(strict_types=1);

namespace App\System\FeatureCatalog\Application\Command\SetClinicFeature;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Switches a feature on or off for one clinic (F2 toggle, the clinic's
 * features card). Answers whether anything changed.
 */
final readonly class SetClinicFeature implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $code,
        public bool $enabled,
        public ?string $actorId,
        public ?string $actorName,
    ) {
    }
}
