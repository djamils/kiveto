<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Infrastructure\Console;

use App\System\PharmaceuticalRegistry\Domain\ValueObject\ImportSource;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class ImportFileManager
{
    public function __construct(
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    public function resolveFile(ImportSource $source, string $filename, ?string $override = null): string
    {
        if (null !== $override) {
            return $override;
        }

        return $this->storageDir($source) . '/current/' . $filename;
    }

    /** Moves amm.xml and dict.xml from current/ to a timestamped archive/ subfolder. */
    public function archiveCurrentFiles(ImportSource $source, \DateTimeImmutable $importedAt): void
    {
        $currentDir  = $this->storageDir($source) . '/current';
        $archiveBase = $this->storageDir($source) . '/archive/' . $importedAt->format('Y-m-d');

        $archiveDir = $archiveBase;
        $suffix     = 2;

        while (is_dir($archiveDir)) {
            $archiveDir = $archiveBase . '_' . $suffix++;
        }

        if (!mkdir($archiveDir, 0755, true) && !is_dir($archiveDir)) {
            throw new \RuntimeException(\sprintf('Cannot create archive directory: %s', $archiveDir));
        }

        foreach (['amm.xml', 'dict.xml'] as $filename) {
            $src = $currentDir . '/' . $filename;

            if (file_exists($src)) {
                rename($src, $archiveDir . '/' . $filename);
            }
        }
    }

    private function storageDir(ImportSource $source): string
    {
        return $this->projectDir . '/storage/pharma-registry/' . $this->sourceToFolder($source);
    }

    private function sourceToFolder(ImportSource $source): string
    {
        return match ($source) {
            ImportSource::ANMV       => 'france',
            ImportSource::EMA_UPD    => 'europe',
            ImportSource::SWISSMEDIC => 'switzerland',
            ImportSource::MHRA       => 'united-kingdom',
            ImportSource::AEMPS      => 'spain',
            ImportSource::BFARM      => 'germany',
        };
    }
}
