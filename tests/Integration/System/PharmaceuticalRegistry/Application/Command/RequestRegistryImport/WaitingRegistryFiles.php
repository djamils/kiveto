<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\PharmaceuticalRegistry\Application\Command\RequestRegistryImport;

use App\System\PharmaceuticalRegistry\Application\Port\RegistryImportFilesInterface;

/**
 * The sample files of the ANMV parser tests as the files waiting, with the
 * hash a test gives them (null: nothing waits); remembers the archives.
 */
final class WaitingRegistryFiles implements RegistryImportFilesInterface
{
    /** @var list<array{string, \DateTimeImmutable}> */
    public array $archived = [];

    public function __construct(
        private readonly ?string $hash,
    ) {
    }

    public function current(string $source): ?array
    {
        if (null === $this->hash) {
            return null;
        }

        $fixtures = \dirname(__DIR__, 6) . '/fixtures/System/PharmaceuticalRegistry';

        return [
            'file'           => $fixtures . '/anmv_sample.xml',
            'dictionary'     => $fixtures . '/dictionary_sample.xml',
            'modifiedAt'     => new \DateTimeImmutable('2026-11-06 06:00:00'),
            'fileHash'       => $this->hash,
            'fileSize'       => 2048,
            'dictionaryHash' => $this->hash . '-dict',
            'dictionarySize' => 512,
        ];
    }

    public function archive(string $source, \DateTimeImmutable $at): array
    {
        $this->archived[] = [$source, $at];

        return ['file' => 'archive/2026-11-06/amm.xml', 'dictionary' => 'archive/2026-11-06/dict.xml'];
    }

    public function kept(string $source, string $storedPath): ?string
    {
        return null;
    }
}
