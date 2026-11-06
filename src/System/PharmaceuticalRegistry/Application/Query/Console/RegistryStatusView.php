<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Query\Console;

/**
 * G1 — the registry today: the authorisations it holds, the latest imports,
 * whether files wait for the next one (and whether they are the very files
 * of the last import).
 */
final readonly class RegistryStatusView
{
    /**
     * @param list<RegistryImportView> $imports newest first
     */
    public function __construct(
        public int $authorizations,
        public array $imports,
        public bool $filesReady,
        public ?\DateTimeImmutable $filesModifiedAt,
        public ?string $filesHash = null,
        public bool $sameAsLast = false,
    ) {
    }

    public function latest(): ?RegistryImportView
    {
        return $this->imports[0] ?? null;
    }

    /** The latest import that went through. */
    public function inService(): ?RegistryImportView
    {
        foreach ($this->imports as $import) {
            if ($import->isApplied()) {
                return $import;
            }
        }

        return null;
    }
}
