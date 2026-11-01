<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\SweepExpiredDataExports;

use App\Context\ClientPortal\Domain\Export\DataExportRepositoryInterface;
use App\Shared\Domain\Storage\FileStorageInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/** At most 500 exports a night: the next night catches up. */
#[AsMessageHandler]
final readonly class SweepExpiredDataExportsHandler
{
    public const int BATCH = 500;

    public function __construct(
        private DataExportRepositoryInterface $exports,
        private FileStorageInterface $storage,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SweepExpiredDataExports $command): void
    {
        foreach ($this->exports->findReadyExpiredAt($this->clock->now(), self::BATCH) as $export) {
            $export->expire();
            $this->storage->delete($export->storageKey());
            $this->exports->save($export);
        }
    }
}
