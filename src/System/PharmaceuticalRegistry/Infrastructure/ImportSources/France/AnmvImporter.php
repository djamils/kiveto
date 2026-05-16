<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Infrastructure\ImportSources\France;

use App\System\PharmaceuticalRegistry\Application\Port\RegistryImporterInterface;
use App\System\PharmaceuticalRegistry\Domain\Repository\SnapshotRepositoryInterface;
use App\System\PharmaceuticalRegistry\Domain\Snapshot;
use App\System\PharmaceuticalRegistry\Infrastructure\ImportSources\Common\AbstractRegistryImporter;

final class AnmvImporter extends AbstractRegistryImporter implements RegistryImporterInterface
{
    public function __construct(
        private readonly AnmvDictionaryLoader $dictionaryLoader,
        private readonly AnmvXmlParser $xmlParser,
        private readonly SnapshotRepositoryInterface $snapshotRepository,
    ) {
    }

    public function stage(Snapshot $snapshot, string $filePath, string $dictionaryPath): void
    {
        $dico = $this->dictionaryLoader->load($dictionaryPath);

        foreach ($this->xmlParser->parse($filePath, $dico) as $dto) {
            $snapshot->addEntry(
                $dto->authorityIdentifier,
                $dto->contentHash,
                $dto->toArray(),
            );
        }

        $this->snapshotRepository->save($snapshot);
    }
}
