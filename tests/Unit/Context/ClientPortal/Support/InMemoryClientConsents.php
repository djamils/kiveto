<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Support;

use App\Context\ClientPortal\Application\Port\ClientConsentChange;
use App\Context\ClientPortal\Application\Port\ClientConsentRecord;
use App\Context\ClientPortal\Application\Port\ClientConsentsInterface;
use App\Context\ClientPortal\Application\Port\ClientConsentWriterInterface;

/**
 * The consents of client files kept in memory; the history is served like
 * the Client context does: `(at DESC, id DESC)`, strictly after a position.
 */
final class InMemoryClientConsents implements ClientConsentsInterface, ClientConsentWriterInterface
{
    /** @var array<string, ClientConsentRecord> by client id */
    public array $records = [];

    /** @var list<ClientConsentChange> */
    public array $history = [];

    /** @var list<array{string, string, string, list<string>, string, string}> clinic, client, kind, channels, user, author */
    public array $writes = [];

    /** @var list<array{list<string>, ?array{at: \DateTimeImmutable, id: string}, int}> */
    public array $historyCalls = [];

    public ?\Throwable $failure = null;

    public function exportOf(string $clinicId, array $clientIds): array
    {
        return ['consents' => [], 'history' => []];
    }

    public function consentsOf(string $clinicId, array $clientIds): array
    {
        return array_intersect_key($this->records, array_flip($clientIds));
    }

    public function historyOf(string $clinicId, array $clientIds, ?array $after, int $limit): array
    {
        $this->historyCalls[] = [$clientIds, $after, $limit];

        $rows = array_filter(
            $this->history,
            static fn (ClientConsentChange $row): bool => \in_array($row->clientId, $clientIds, true)
                && (null === $after || $row->at < $after['at'] || ($row->at->getTimestamp() === $after['at']->getTimestamp() && strcmp($row->id, $after['id']) < 0)),
        );
        usort($rows, static fn (ClientConsentChange $a, ClientConsentChange $b): int => [$b->at, $b->id] <=> [$a->at, $a->id]);

        return \array_slice($rows, 0, $limit);
    }

    public function setConsent(string $clinicId, string $clientId, string $kind, array $channels, string $userId, string $authorLabel): void
    {
        if (null !== $this->failure) {
            throw $this->failure;
        }

        $this->writes[] = [$clinicId, $clientId, $kind, $channels, $userId, $authorLabel];
    }
}
