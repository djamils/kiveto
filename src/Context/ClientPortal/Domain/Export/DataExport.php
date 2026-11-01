<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Export;

/**
 * An owner's request for a copy of the data the Portal holds about the
 * account (GDPR access and portability): a ZIP built asynchronously, kept
 * 48 hours under `client_portal/exports/<id>.zip`, then deleted. Each step
 * moves only from the expected state, so a late or replayed message
 * changes nothing.
 */
final class DataExport
{
    public const string KEY_PREFIX = 'client_portal/exports/';

    /** How long a ready archive can be downloaded. */
    public const string RETENTION = 'PT48H';

    private DataExportId $id;
    private string $userId;
    private DataExportStatus $status;
    private \DateTimeImmutable $requestedAt;
    private ?\DateTimeImmutable $readyAt;
    private ?\DateTimeImmutable $expiresAt;
    private ?\DateTimeImmutable $failedAt;

    private function __construct()
    {
    }

    public static function request(DataExportId $id, string $userId, \DateTimeImmutable $now): self
    {
        $export              = new self();
        $export->id          = $id;
        $export->userId      = $userId;
        $export->status      = DataExportStatus::PENDING;
        $export->requestedAt = $now;
        $export->readyAt     = null;
        $export->expiresAt   = null;
        $export->failedAt    = null;

        return $export;
    }

    public static function reconstitute(
        DataExportId $id,
        string $userId,
        DataExportStatus $status,
        \DateTimeImmutable $requestedAt,
        ?\DateTimeImmutable $readyAt,
        ?\DateTimeImmutable $expiresAt,
        ?\DateTimeImmutable $failedAt,
    ): self {
        $export              = new self();
        $export->id          = $id;
        $export->userId      = $userId;
        $export->status      = $status;
        $export->requestedAt = $requestedAt;
        $export->readyAt     = $readyAt;
        $export->expiresAt   = $expiresAt;
        $export->failedAt    = $failedAt;

        return $export;
    }

    /** The storage key of an export's archive. */
    public static function keyOf(string $exportId): string
    {
        return self::KEY_PREFIX . $exportId . '.zip';
    }

    /**
     * The archive is stored: downloadable for 48 hours.
     *
     * @return bool false when the export was no longer pending (nothing changed)
     */
    public function markReady(\DateTimeImmutable $now): bool
    {
        if (DataExportStatus::PENDING !== $this->status) {
            return false;
        }

        $this->status    = DataExportStatus::READY;
        $this->readyAt   = $now;
        $this->expiresAt = $now->add(new \DateInterval(self::RETENTION));

        return true;
    }

    /** @return bool false when the export was no longer pending (nothing changed) */
    public function markFailed(\DateTimeImmutable $now): bool
    {
        if (DataExportStatus::PENDING !== $this->status) {
            return false;
        }

        $this->status   = DataExportStatus::FAILED;
        $this->failedAt = $now;

        return true;
    }

    /**
     * The archive was deleted.
     *
     * @return bool false when the export was not ready (nothing changed)
     */
    public function expire(): bool
    {
        if (DataExportStatus::READY !== $this->status) {
            return false;
        }

        $this->status = DataExportStatus::EXPIRED;

        return true;
    }

    /** The status as the owner sees it: a ready export past its 48 hours is expired. */
    public function statusAt(\DateTimeImmutable $now): DataExportStatus
    {
        if (DataExportStatus::READY === $this->status && null !== $this->expiresAt && $now >= $this->expiresAt) {
            return DataExportStatus::EXPIRED;
        }

        return $this->status;
    }

    public function isDownloadableAt(\DateTimeImmutable $now): bool
    {
        return DataExportStatus::READY === $this->statusAt($now);
    }

    public function storageKey(): string
    {
        return self::keyOf($this->id->toString());
    }

    public function id(): DataExportId
    {
        return $this->id;
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function status(): DataExportStatus
    {
        return $this->status;
    }

    public function requestedAt(): \DateTimeImmutable
    {
        return $this->requestedAt;
    }

    public function readyAt(): ?\DateTimeImmutable
    {
        return $this->readyAt;
    }

    public function expiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function failedAt(): ?\DateTimeImmutable
    {
        return $this->failedAt;
    }
}
