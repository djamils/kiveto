<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Adapter\Client;

use App\Context\Client\Application\Query\GetClientConsents\ClientConsentsView;
use App\Context\Client\Application\Query\GetClientConsents\GetClientConsents;
use App\Context\Client\Application\Query\ListClientConsentHistory\ConsentHistoryItemView;
use App\Context\Client\Application\Query\ListClientConsentHistory\ListClientConsentHistory;
use App\Context\ClientPortal\Application\Port\ClientConsentChange;
use App\Context\ClientPortal\Application\Port\ClientConsentGrant;
use App\Context\ClientPortal\Application\Port\ClientConsentRecord;
use App\Context\ClientPortal\Application\Port\ClientConsentsInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Pagination\Cursor;
use App\Shared\Application\Pagination\CursorPage;

/**
 * Client's GetClientConsents and ListClientConsentHistory (read page by
 * page to the end). Consents are merged per kind across the client files,
 * channels in `sms`, `email` order. A history position is handed to
 * ListClientConsentHistory as its cursor: `(at, id)`, `at` in UTC to the
 * second.
 */
final readonly class QueryBusClientConsents implements ClientConsentsInterface
{
    private const int PAGE = 50;

    private const array CHANNEL_ORDER = ['sms' => 0, 'email' => 1];

    private const string CURSOR_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function exportOf(string $clinicId, array $clientIds): array
    {
        return ['consents' => $this->consents($clinicId, $clientIds), 'history' => $this->history($clinicId, $clientIds)];
    }

    public function consentsOf(string $clinicId, array $clientIds): array
    {
        $records = $this->queryBus->ask(new GetClientConsents($clinicId, $clientIds));
        \assert(\is_array($records));
        $views = [];

        foreach ($records as $record) {
            \assert($record instanceof ClientConsentsView);
            $grants = [];

            foreach ($record->consents as $kind => $consent) {
                $grants[$kind] = new ClientConsentGrant($consent->kind, $consent->channels, $consent->grantedAt);
            }

            $views[$record->clientId] = new ClientConsentRecord($record->clientId, $record->preferredChannel, $record->archived, $grants);
        }

        return $views;
    }

    public function historyOf(string $clinicId, array $clientIds, ?array $after, int $limit): array
    {
        $cursor = null === $after ? null : Cursor::fromPosition([
            'at' => $after['at']->setTimezone(new \DateTimeZone('UTC'))->format(self::CURSOR_FORMAT),
            'id' => $after['id'],
        ])->encode();

        $page = $this->queryBus->ask(new ListClientConsentHistory($clinicId, $clientIds, $cursor, $limit));
        \assert($page instanceof CursorPage);

        return array_map(static function (mixed $item): ClientConsentChange {
            \assert($item instanceof ConsentHistoryItemView);

            return new ClientConsentChange($item->id, $item->clientId, $item->kind, $item->channel, $item->action, $item->at, $item->source, $item->actorType, $item->authorLabel, $item->approximate);
        }, $page->items);
    }

    /**
     * @param list<string> $clientIds
     *
     * @return list<array{kind: string, channels: list<string>}>
     */
    private function consents(string $clinicId, array $clientIds): array
    {
        $records = $this->queryBus->ask(new GetClientConsents($clinicId, $clientIds));
        \assert(\is_array($records));
        $channels = [];

        foreach ($records as $record) {
            \assert($record instanceof ClientConsentsView);

            foreach ($record->consents as $consent) {
                $channels[$consent->kind] = array_values(array_unique([...$channels[$consent->kind] ?? [], ...$consent->channels]));
            }
        }

        ksort($channels);
        $consents = [];

        foreach ($channels as $kind => $granted) {
            usort($granted, static fn (string $a, string $b): int => (self::CHANNEL_ORDER[$a] ?? 2) <=> (self::CHANNEL_ORDER[$b] ?? 2));
            $consents[] = ['kind' => (string) $kind, 'channels' => $granted];
        }

        return $consents;
    }

    /**
     * @param list<string> $clientIds
     *
     * @return list<array{kind: string, channel: string, action: string, source: string, at: \DateTimeImmutable}>
     */
    private function history(string $clinicId, array $clientIds): array
    {
        $history = [];
        $cursor  = null;

        do {
            $page = $this->queryBus->ask(new ListClientConsentHistory($clinicId, $clientIds, $cursor, self::PAGE));
            \assert($page instanceof CursorPage);

            foreach ($page->items as $item) {
                \assert($item instanceof ConsentHistoryItemView);
                $history[] = ['kind' => $item->kind, 'channel' => $item->channel, 'action' => $item->action, 'source' => $item->source, 'at' => $item->at];
            }

            $cursor = $page->nextCursor;
        } while (null !== $cursor);

        return $history;
    }
}
