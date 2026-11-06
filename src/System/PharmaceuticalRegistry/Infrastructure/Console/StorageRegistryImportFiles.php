<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Infrastructure\Console;

use App\System\PharmaceuticalRegistry\Application\Port\RegistryImportFilesInterface;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ImportSource;

/**
 * The files of the next import where the import command looks for them
 * (ImportFileManager: storage/pharma-registry/<source>/current/), and the
 * archive where each import keeps its own (archive/<date>/).
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

        return [
            'file'           => $file,
            'dictionary'     => $dictionary,
            'modifiedAt'     => (new \DateTimeImmutable('@' . $modified))->setTimezone(new \DateTimeZone('UTC')),
            'fileHash'       => (string) hash_file('sha256', $file),
            'fileSize'       => (int) filesize($file),
            'dictionaryHash' => (string) hash_file('sha256', $dictionary),
            'dictionarySize' => (int) filesize($dictionary),
        ];
    }

    public function archive(string $source, \DateTimeImmutable $at): array
    {
        $folder = $this->files->archiveCurrentFiles(ImportSource::from($source), $at);

        return ['file' => $folder . '/amm.xml', 'dictionary' => $folder . '/dict.xml'];
    }

    public function kept(string $source, string $storedPath): ?string
    {
        return $this->files->keptFile(ImportSource::from($source), $storedPath);
    }
}
