<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Query\Console;

use App\System\PharmaceuticalRegistry\Application\Port\RegistryImportFileLogInterface;
use App\System\PharmaceuticalRegistry\Application\Port\RegistryImportFilesInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetRegistryImportFileHandler
{
    public function __construct(
        private RegistryImportFileLogInterface $log,
        private RegistryImportFilesInterface $files,
    ) {
    }

    /**
     * @return array{path: string, name: string, sha256: string}|null the file, and « ANMV-2026-11-06-amm.xml »
     */
    public function __invoke(GetRegistryImportFile $query): ?array
    {
        foreach ($this->log->filesOf([$query->importId])[$query->importId] ?? [] as $file) {
            if ($file->kind !== $query->kind) {
                continue;
            }

            $path = $this->files->kept($query->source, $file->storedPath);

            return null === $path ? null : [
                'path'   => $path,
                'name'   => \sprintf('%s-%s-%s', $query->source, $file->receivedAt->format('Y-m-d'), $file->name),
                'sha256' => $file->sha256,
            ];
        }

        return null;
    }
}
