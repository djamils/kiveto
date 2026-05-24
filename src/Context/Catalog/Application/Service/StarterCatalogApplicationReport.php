<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Service;

final readonly class StarterCatalogApplicationReport
{
    /**
     * @param list<string> $created
     * @param list<string> $skipped
     * @param list<string> $failed
     */
    public function __construct(
        public array $created,
        public array $skipped,
        public array $failed,
    ) {
    }
}
