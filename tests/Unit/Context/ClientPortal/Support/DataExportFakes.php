<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Support;

use App\Context\ClientPortal\Application\Port\ClientConsentsInterface;
use App\Context\ClientPortal\Application\Port\DataExportBuilderInterface;
use App\Context\ClientPortal\Application\Port\DataExportJobsInterface;
use App\Context\ClientPortal\Application\Port\DataExportQuotaInterface;
use App\Context\ClientPortal\Application\Port\OwnerAccount;
use App\Context\ClientPortal\Application\Port\OwnerAccountsInterface;
use App\Context\ClientPortal\Domain\Exception\DataExportRateLimitedException;

/**
 * The data export's collaborators on public fields: a quota of one export
 * per user (refunds counted), the queued jobs, a builder answering a fixed
 * archive (or failing), owner accounts and client consents by id.
 */
final class DataExportFakes implements DataExportQuotaInterface, DataExportJobsInterface, DataExportBuilderInterface, OwnerAccountsInterface, ClientConsentsInterface
{
    /** @var array<string, true> users whose export is used */
    public array $used = [];

    /** @var list<string> */
    public array $refunds = [];

    /** @var list<string> */
    public array $builds = [];

    /** @var list<string> */
    public array $deletions = [];

    public ?\Throwable $buildFailure = null;

    /** @var array<string, OwnerAccount> */
    public array $accounts = [];

    /**
     * @var array<string, array{
     *     consents: list<array{kind: string, channels: list<string>}>,
     *     history: list<array{kind: string, channel: string, action: string, source: string, at: \DateTimeImmutable}>
     * }> by clinic id
     */
    public array $consents = [];

    /** @var list<string> */
    public array $consentCalls = [];

    public function consume(string $userId): void
    {
        if (isset($this->used[$userId])) {
            throw new DataExportRateLimitedException(86_400);
        }

        $this->used[$userId] = true;
    }

    public function refund(string $userId): void
    {
        unset($this->used[$userId]);
        $this->refunds[] = $userId;
    }

    public function queueBuild(string $exportId): void
    {
        $this->builds[] = $exportId;
    }

    public function deleteFileLater(string $exportId): void
    {
        $this->deletions[] = $exportId;
    }

    public function accountOf(string $userId): ?OwnerAccount
    {
        return $this->accounts[$userId] ?? null;
    }

    public function exportOf(string $clinicId, array $clientIds): array
    {
        $this->consentCalls[] = $clinicId . ':' . implode(',', $clientIds);

        return $this->consents[$clinicId] ?? ['consents' => [], 'history' => []];
    }

    public function consentsOf(string $clinicId, array $clientIds): array
    {
        return [];
    }

    public function historyOf(string $clinicId, array $clientIds, ?array $after, int $limit): array
    {
        return [];
    }

    public function build(string $userId): string
    {
        if (null !== $this->buildFailure) {
            throw $this->buildFailure;
        }

        return 'ZIP of ' . $userId;
    }
}
