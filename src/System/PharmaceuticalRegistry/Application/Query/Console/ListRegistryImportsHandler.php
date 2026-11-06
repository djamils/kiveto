<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Query\Console;

use App\System\PharmaceuticalRegistry\Application\Port\RegistryImportFileLogInterface;
use App\System\PharmaceuticalRegistry\Application\Port\RegistryImportFilesInterface;
use App\System\PharmaceuticalRegistry\Application\Port\RegistryImportReadRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListRegistryImportsHandler
{
    public function __construct(
        private RegistryImportReadRepositoryInterface $imports,
        private RegistryImportFilesInterface $files,
        private RegistryImportFileLogInterface $log,
    ) {
    }

    public function __invoke(ListRegistryImports $query): RegistryStatusView
    {
        $waiting = $this->files->current($query->source);
        $imports = $this->imports->imports($query->source, max(1, $query->limit));
        $kept    = $this->log->filesOf(array_map(static fn (RegistryImportView $i): string => $i->id, $imports));

        return new RegistryStatusView(
            authorizations: $this->imports->authorizationCount(),
            imports: array_map(static fn (RegistryImportView $i): RegistryImportView => $i->withFiles($kept[$i->id] ?? []), $imports),
            filesReady: null !== $waiting,
            filesModifiedAt: $waiting['modifiedAt'] ?? null,
            filesHash: $waiting['fileHash'] ?? null,
            sameAsLast: null !== $waiting && $waiting['fileHash'] === $this->log->lastAppliedRegistryHash($query->source),
        );
    }
}
