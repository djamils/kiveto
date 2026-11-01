<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListPortalConsentHistory;

use App\Context\ClientPortal\Application\Port\ClientConsentChange;
use App\Context\ClientPortal\Application\Port\ClientConsentRecord;
use App\Context\ClientPortal\Application\Port\ClientConsentsInterface;
use App\Context\ClientPortal\Application\Service\PortalConsentFiles;
use App\Shared\Application\Pagination\Cursor;
use App\Shared\Application\Pagination\CursorPage;
use App\Shared\Application\Pagination\InvalidCursorException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * The rows of the files with the same `(kind, channel, action, at, source)`
 * are one line: a change applied to two files is shown once. The rows of
 * one instant are contiguous on `(at DESC, id DESC)`, so lines are built
 * per instant, ordered by the greatest id of their rows; the cursor is
 * `(at, id)` of the last line shown. A next page reads its cursor's instant
 * again from the start and drops the lines already shown (an id not below
 * the cursor's), so a line never splits across two pages.
 */
#[AsMessageHandler]
final readonly class ListPortalConsentHistoryHandler
{
    /** Rows read per call to the Client context (its maximum page). */
    private const int BATCH = Cursor::MAX_LIMIT;

    private const string FORMAT = 'Y-m-d H:i:s';

    /** Above every row id: a position that keeps the whole instant. */
    private const string INSTANT_START = 'ffffffff-ffff-ffff-ffff-ffffffffffff';

    private const string CLINIC_AUTHOR = 'La clinique';

    public function __construct(
        private PortalConsentFiles $files,
        private ClientConsentsInterface $consents,
    ) {
    }

    /**
     * @return CursorPage<PortalConsentHistoryItem>
     */
    public function __invoke(ListPortalConsentHistory $query): CursorPage
    {
        $limit     = Cursor::clampLimit($query->limit);
        $position  = null === $query->cursor ? null : self::position($query->cursor);
        $clientIds = array_map(static fn (ClientConsentRecord $record): string => $record->clientId, $this->files->activeOf($query->userId, $query->clinicId));
        $after     = null === $position ? null : ['at' => $position['at'], 'id' => self::INSTANT_START];

        /** @var array<string, array{id: string, rows: non-empty-list<ClientConsentChange>}> $lines */
        $lines   = [];
        $skipped = [];

        do {
            $rows = $this->consents->historyOf($query->clinicId, $clientIds, $after, self::BATCH);

            foreach ($rows as $row) {
                $key = implode('|', [$row->at->getTimestamp(), $row->kind, $row->channel, $row->action, $row->source]);

                if (isset($skipped[$key])) {
                    continue;
                }

                if (isset($lines[$key])) {
                    $lines[$key]['rows'][] = $row;
                    continue;
                }

                // The first row of a line has its greatest id: a line of the cursor's instant at or above it was shown.
                if (null !== $position && $row->at->getTimestamp() === $position['at']->getTimestamp() && strcmp($row->id, $position['id']) >= 0) {
                    $skipped[$key] = true;
                    continue;
                }

                $lines[$key] = ['id' => $row->id, 'rows' => [$row]];
            }

            $last  = $rows[\count($rows) - 1] ?? null;
            $after = null === $last ? null : ['at' => $last->at, 'id' => $last->id];
        } while (self::BATCH === \count($rows) && !self::complete(array_values($lines), $limit, $last));

        $lines = array_values($lines);
        $next  = null;

        if (\count($lines) > $limit) {
            $lines = \array_slice($lines, 0, $limit);
            $edge  = $lines[$limit - 1];
            $next  = Cursor::fromPosition(['at' => self::utc($edge['rows'][0]->at), 'id' => $edge['id']])->encode();
        }

        return new CursorPage(array_map(self::item(...), $lines), $next);
    }

    /**
     * The page is known once a line follows its last one and a row of an
     * earlier instant was read: that last line has all its rows.
     *
     * @param list<array{id: string, rows: non-empty-list<ClientConsentChange>}> $lines
     */
    private static function complete(array $lines, int $limit, ?ClientConsentChange $last): bool
    {
        return \count($lines) > $limit && null !== $last && $last->at < $lines[$limit - 1]['rows'][0]->at;
    }

    /**
     * @param array{id: string, rows: non-empty-list<ClientConsentChange>} $line
     */
    private static function item(array $line): PortalConsentHistoryItem
    {
        $first = $line['rows'][0];

        return new PortalConsentHistoryItem(
            $first->kind,
            $first->channel,
            $first->action,
            $first->at,
            $first->source,
            'clinic' === $first->source ? self::CLINIC_AUTHOR : $first->authorLabel,
            array_any($line['rows'], static fn (ClientConsentChange $row): bool => $row->approximate),
        );
    }

    /**
     * @throws InvalidCursorException
     *
     * @return array{at: \DateTimeImmutable, id: string}
     */
    private static function position(string $cursor): array
    {
        $position = Cursor::decode($cursor)->position();
        $at       = $position['at'] ?? null;
        $id       = $position['id'] ?? null;

        if (!\is_string($at) || !\is_string($id) || 1 !== preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $id)) {
            throw new InvalidCursorException();
        }

        $date = \DateTimeImmutable::createFromFormat('!' . self::FORMAT, $at, new \DateTimeZone('UTC'));

        if (false === $date || $date->format(self::FORMAT) !== $at) {
            throw new InvalidCursorException();
        }

        return ['at' => $date, 'id' => $id];
    }

    private static function utc(\DateTimeImmutable $at): string
    {
        return $at->setTimezone(new \DateTimeZone('UTC'))->format(self::FORMAT);
    }
}
