<?php

declare(strict_types=1);

namespace App\System\FeatureCatalog\Application\Command\CutFeature;

use App\Shared\Application\Bus\CommandInterface;

/**
 * F2 « Couper partout » (the kill switch): no clinic has the feature until
 * it is restored, whatever was set for each one.
 */
final readonly class CutFeature implements CommandInterface
{
    public function __construct(
        public string $code,
        public string $reason,
        public ?string $actorName,
    ) {
    }
}
