<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\SweepExpiredDataExports;

use App\Context\ClientPortal\Application\Port\DataExportQuotaInterface;
use App\Context\ClientPortal\Domain\Export\DataExportRepositoryInterface;
use App\Shared\Domain\Storage\FileStorageInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * At most 500 exports of each kind a night: the next night catches up. An
 * export pending for more than 6 hours (its build or outcome was lost) is
 * given up like a failed build: `failed`, quota refunded (only on that
 * transition, so never twice), any archive stored deleted. A late outcome
 * then finds it failed (RecordDataExportHandler deletes a late archive).
 */
#[AsMessageHandler]
final readonly class SweepExpiredDataExportsHandler
{
    public const int    BATCH       = 500;
    public const string STUCK_AFTER = '-6 hours';

    public function __construct(
        private DataExportRepositoryInterface $exports,
        private FileStorageInterface $storage,
        private DataExportQuotaInterface $quota,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SweepExpiredDataExports $command): void
    {
        $now = $this->clock->now();

        foreach ($this->exports->findReadyExpiredForUpdate($now, self::BATCH) as $export) {
            $export->expire();
            $this->storage->delete($export->storageKey());
            $this->exports->update($export);
        }

        foreach ($this->exports->findPendingRequestedBeforeForUpdate($now->modify(self::STUCK_AFTER), self::BATCH) as $export) {
            // Locked and still pending: this is its only transition to failed
            $export->markFailed($now);
            $this->storage->delete($export->storageKey());
            $this->exports->update($export);
            $this->quota->refund($export->userId());
        }
    }
}
