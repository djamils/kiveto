<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Query\Console;

use App\System\PharmaceuticalRegistry\Application\Port\RegistryImportFileLogInterface;
use App\System\PharmaceuticalRegistry\Application\Port\RegistryImportReadRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetRegistryImportHandler
{
    private const array KINDS = ['CREATE', 'UPDATE', 'WITHDRAW'];

    public function __construct(
        private RegistryImportReadRepositoryInterface $imports,
        private RegistryImportFileLogInterface $log,
    ) {
    }

    public function __invoke(GetRegistryImport $query): ?RegistryImportDetail
    {
        $import = $this->imports->import($query->id);

        if (null === $import) {
            return null;
        }

        $kind    = \in_array($query->kind, self::KINDS, true) ? $query->kind : null;
        $perPage = max(1, $query->perPage);
        $total   = $this->imports->countDiff($query->id, $kind);
        $page    = min(max(1, $query->page), max(1, (int) ceil($total / $perPage)));

        return new RegistryImportDetail(
            import: $import->withFiles($this->log->filesOf([$import->id])[$import->id] ?? []),
            lines: 0 === $total ? [] : $this->imports->diff($query->id, $kind, ($page - 1) * $perPage, $perPage),
            total: $total,
            page: $page,
            perPage: $perPage,
        );
    }
}
