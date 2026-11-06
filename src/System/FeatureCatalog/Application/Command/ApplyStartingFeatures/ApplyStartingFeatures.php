<?php

declare(strict_types=1);

namespace App\System\FeatureCatalog\Application\Command\ApplyStartingFeatures;

use App\Shared\Application\Bus\CommandInterface;

/**
 * B5 — a new clinic starts with the features ticked at the third step: each
 * feature that exists is set, on when ticked, off otherwise.
 */
final readonly class ApplyStartingFeatures implements CommandInterface
{
    /**
     * @param list<string> $enabledCodes
     */
    public function __construct(
        public string $clinicId,
        public array $enabledCodes,
        public ?string $actorId,
        public ?string $actorName,
    ) {
    }
}
