<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetDataExportFile;

use App\Context\ClientPortal\Domain\Export\DataExport;
use App\Context\ClientPortal\Domain\Export\DataExportRepositoryInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetDataExportFileHandler
{
    public function __construct(
        private DataExportRepositoryInterface $exports,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(GetDataExportFile $query): ?DataExportFile
    {
        $export = $this->exports->findById($query->exportId);

        if (null === $export || $export->userId() !== $query->userId || !$export->isDownloadableAt($this->clock->now())) {
            return null;
        }

        return new DataExportFile($export->id()->toString(), $export->storageKey());
    }

    /** The storage key of an export's archive, for its signed download. */
    public static function storageKey(string $exportId): string
    {
        return DataExport::keyOf($exportId);
    }
}
