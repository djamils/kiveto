<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Query\Console;

final readonly class RegistryImportDetail
{
    /**
     * @param list<RegistryDiffLine> $lines
     */
    public function __construct(
        public RegistryImportView $import,
        public array $lines,
        public int $total,
        public int $page,
        public int $perPage,
    ) {
    }

    public function pageCount(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }
}
