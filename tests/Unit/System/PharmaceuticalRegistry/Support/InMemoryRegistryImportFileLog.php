<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\PharmaceuticalRegistry\Support;

use App\System\PharmaceuticalRegistry\Application\Port\RegistryImportFileLogInterface;
use App\System\PharmaceuticalRegistry\Application\Query\Console\RegistryImportFile;

/**
 * The journal of the imports' files, in memory; `lastApplied` is the hash
 * of the last import that went through, as a test sets it.
 */
final class InMemoryRegistryImportFileLog implements RegistryImportFileLogInterface
{
    /** @var array<string, list<RegistryImportFile>> */
    public array $files = [];

    /** @var array<string, string> by snapshot id */
    public array $sources = [];

    public function __construct(
        public ?string $lastApplied = null,
    ) {
    }

    public function record(string $snapshotId, string $source, array $files): void
    {
        $this->files[$snapshotId]   = [...$this->files[$snapshotId] ?? [], ...$files];
        $this->sources[$snapshotId] = $source;
    }

    public function filesOf(array $snapshotIds): array
    {
        return array_intersect_key($this->files, array_flip($snapshotIds));
    }

    public function lastAppliedRegistryHash(string $source): ?string
    {
        return $this->lastApplied;
    }
}
