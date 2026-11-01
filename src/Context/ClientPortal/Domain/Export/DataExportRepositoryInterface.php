<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Export;

interface DataExportRepositoryInterface
{
    public function save(DataExport $export): void;

    public function findById(string $exportId): ?DataExport;

    /**
     * Every export of an account, latest request first.
     *
     * @return list<DataExport>
     */
    public function findByUser(string $userId): array;

    /**
     * Ready exports whose 48 hours are over, oldest first.
     *
     * @return list<DataExport>
     */
    public function findReadyExpiredAt(\DateTimeImmutable $now, int $limit): array;

    public function remove(DataExport $export): void;
}
