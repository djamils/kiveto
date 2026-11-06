<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Query\Console;

use App\Shared\Application\Bus\QueryInterface;

/**
 * G4 — one import and a page of its changes (`kind`: CREATE, UPDATE,
 * WITHDRAW, or all). Answers a RegistryImportDetail, null when unknown.
 */
final readonly class GetRegistryImport implements QueryInterface
{
    public function __construct(
        public string $id,
        public ?string $kind = null,
        public int $page = 1,
        public int $perPage = 10,
    ) {
    }
}
