<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Query\Console;

use App\System\PharmaceuticalRegistry\Application\Port\RegistryImportFilesInterface;
use App\System\PharmaceuticalRegistry\Application\Port\RegistryImportReadRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListRegistryImportsHandler
{
    public function __construct(
        private RegistryImportReadRepositoryInterface $imports,
        private RegistryImportFilesInterface $files,
    ) {
    }

    public function __invoke(ListRegistryImports $query): RegistryStatusView
    {
        $files = $this->files->current($query->source);

        return new RegistryStatusView(
            authorizations: $this->imports->authorizationCount(),
            imports: $this->imports->imports($query->source, max(1, $query->limit)),
            filesReady: null !== $files,
            filesModifiedAt: $files['modifiedAt'] ?? null,
        );
    }
}
