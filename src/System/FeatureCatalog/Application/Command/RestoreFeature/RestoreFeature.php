<?php

declare(strict_types=1);

namespace App\System\FeatureCatalog\Application\Command\RestoreFeature;

use App\Shared\Application\Bus\CommandInterface;

/**
 * F2 « Remettre en service »: the end of a cut; each clinic has the feature
 * again as it had it before.
 */
final readonly class RestoreFeature implements CommandInterface
{
    public function __construct(
        public string $code,
    ) {
    }
}
