<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Support;

use App\Context\ClientPortal\Domain\Export\DataExport;
use App\Context\ClientPortal\Domain\Export\DataExportRepositoryInterface;
use App\Context\ClientPortal\Domain\Export\DataExportStatus;

final class InMemoryDataExports implements DataExportRepositoryInterface
{
    /** @var array<string, DataExport> by id */
    public array $exports = [];

    public int $saves = 0;

    public function add(DataExport $export): void
    {
        $this->exports[$export->id()->toString()] = $export;
        ++$this->saves;
    }

    public function update(DataExport $export): void
    {
        // A removed row stays removed
        if (isset($this->exports[$export->id()->toString()])) {
            $this->exports[$export->id()->toString()] = $export;
            ++$this->saves;
        }
    }

    public function findById(string $exportId): ?DataExport
    {
        return $this->exports[$exportId] ?? null;
    }

    public function findByIdForUpdate(string $exportId): ?DataExport
    {
        return $this->findById($exportId);
    }

    public function findByUserForUpdate(string $userId): array
    {
        return $this->findByUser($userId);
    }

    public function findPendingRequestedBeforeForUpdate(\DateTimeImmutable $requestedBefore, int $limit): array
    {
        return \array_slice(array_values(array_filter(
            $this->exports,
            static fn (DataExport $e): bool => DataExportStatus::PENDING === $e->status() && $e->requestedAt() < $requestedBefore,
        )), 0, $limit);
    }

    public function findByUser(string $userId): array
    {
        $exports = array_values(array_filter($this->exports, static fn (DataExport $e): bool => $e->userId() === $userId));
        usort($exports, static fn (DataExport $a, DataExport $b): int => $b->requestedAt() <=> $a->requestedAt());

        return $exports;
    }

    public function findReadyExpiredForUpdate(\DateTimeImmutable $now, int $limit): array
    {
        return \array_slice(array_values(array_filter(
            $this->exports,
            static fn (DataExport $e): bool => DataExportStatus::READY === $e->status() && $e->expiresAt() <= $now,
        )), 0, $limit);
    }

    public function remove(DataExport $export): void
    {
        unset($this->exports[$export->id()->toString()]);
    }
}
