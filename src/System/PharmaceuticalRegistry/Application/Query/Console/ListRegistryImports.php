<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Query\Console;

use App\Shared\Application\Bus\QueryInterface;

/**
 * G1 — the registry's state and its latest imports. Answers a RegistryStatusView.
 */
final readonly class ListRegistryImports implements QueryInterface
{
    public function __construct(
        public int $limit = 8,
        public string $source = 'ANMV',
    ) {
    }
}
