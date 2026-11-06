<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Query\Console;

/**
 * A source file of an import, kept for the record (G3 « Fichiers source »):
 * the registry file ('amm') or its dictionary ('dict'), its SHA-256, its
 * size, when it was dropped, where it is kept, who launched the import.
 */
final readonly class RegistryImportFile
{
    public const string REGISTRY   = 'amm';
    public const string DICTIONARY = 'dict';

    public function __construct(
        public string $kind,
        public string $name,
        public string $sha256,
        public int $size,
        public string $storedPath,
        public \DateTimeImmutable $receivedAt,
        public ?string $importedBy,
    ) {
    }

    /**
     * The two files of an import, from what was waiting and where it was kept.
     *
     * @param array{file: string, dictionary: string, modifiedAt: \DateTimeImmutable, fileHash: string, fileSize: int, dictionaryHash: string, dictionarySize: int} $waiting
     * @param array{file: string, dictionary: string}                                                                                                               $kept
     *
     * @return list<self>
     */
    public static function pair(array $waiting, array $kept, ?string $importedBy): array
    {
        return [
            new self(self::REGISTRY, basename($kept['file']), $waiting['fileHash'], $waiting['fileSize'], $kept['file'], $waiting['modifiedAt'], $importedBy),
            new self(self::DICTIONARY, basename($kept['dictionary']), $waiting['dictionaryHash'], $waiting['dictionarySize'], $kept['dictionary'], $waiting['modifiedAt'], $importedBy),
        ];
    }

    /** « 3,4 Mo », « 812 Ko ». */
    public function sizeLabel(): string
    {
        return match (true) {
            $this->size >= 1_048_576 => str_replace('.', ',', \sprintf('%.1f Mo', $this->size / 1_048_576)),
            $this->size >= 1024      => \sprintf('%d Ko', intdiv($this->size, 1024)),
            default                  => \sprintf('%d o', $this->size),
        };
    }
}
