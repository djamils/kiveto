<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Export;

/**
 * A new export is inserted once; every transition is a plain update of a
 * row loaded for update, so a row removed meanwhile (account deletion)
 * stays removed.
 */
interface DataExportRepositoryInterface
{
    public function add(DataExport $export): void;

    /** Writes the state of an existing row; a removed row stays removed. */
    public function update(DataExport $export): void;

    public function findById(string $exportId): ?DataExport;

    /** The export, locked until the transaction ends. */
    public function findByIdForUpdate(string $exportId): ?DataExport;

    /**
     * Every export of an account, latest request first.
     *
     * @return list<DataExport>
     */
    public function findByUser(string $userId): array;

    /**
     * Every export of an account, locked until the transaction ends.
     *
     * @return list<DataExport>
     */
    public function findByUserForUpdate(string $userId): array;

    /**
     * Ready exports whose 48 hours are over, oldest first; locked, a row
     * locked by another writer skipped.
     *
     * @return list<DataExport>
     */
    public function findReadyExpiredForUpdate(\DateTimeImmutable $now, int $limit): array;

    /**
     * Exports still pending although requested before `$requestedBefore`
     * (their build or its outcome was lost), oldest first; locked like the
     * expired ones.
     *
     * @return list<DataExport>
     */
    public function findPendingRequestedBeforeForUpdate(\DateTimeImmutable $requestedBefore, int $limit): array;

    public function remove(DataExport $export): void;
}
