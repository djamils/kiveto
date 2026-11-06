<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Infrastructure\Console;

use App\System\PharmaceuticalRegistry\Application\Port\RegistryImportFilesInterface;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ImportSource;

/**
 * The files of the next import where the import command looks for them
 * (ImportFileManager: storage/pharma-registry/<source>/current/).
 */
final readonly class StorageRegistryImportFiles implements RegistryImportFilesInterface
{
    public function __construct(
        private ImportFileManager $files,
    ) {
    }

    public function current(string $source): ?array
    {
        $from       = ImportSource::from($source);
        $file       = $this->files->resolveFile($from, 'amm.xml');
        $dictionary = $this->files->resolveFile($from, 'dict.xml');
        $modified   = is_file($file) && is_file($dictionary) ? filemtime($file) : false;

        if (false === $modified) {
            return null;
        }

        return ['file' => $file, 'dictionary' => $dictionary, 'modifiedAt' => (new \DateTimeImmutable('@' . $modified))->setTimezone(new \DateTimeZone('UTC'))];
    }

    public function archive(string $source, \DateTimeImmutable $at): void
    {
        $this->files->archiveCurrentFiles(ImportSource::from($source), $at);
    }
}
