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

    public function save(DataExport $export): void
    {
        $this->exports[$export->id()->toString()] = $export;
        ++$this->saves;
    }

    public function findById(string $exportId): ?DataExport
    {
        return $this->exports[$exportId] ?? null;
    }

    public function findByUser(string $userId): array
    {
        $exports = array_values(array_filter($this->exports, static fn (DataExport $e): bool => $e->userId() === $userId));
        usort($exports, static fn (DataExport $a, DataExport $b): int => $b->requestedAt() <=> $a->requestedAt());

        return $exports;
    }

    public function findReadyExpiredAt(\DateTimeImmutable $now, int $limit): array
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
