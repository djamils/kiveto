<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\ListClientConsentHistory;

use App\Context\Client\Application\Port\ClientConsentReaderInterface;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Shared\Application\Pagination\Cursor;
use App\Shared\Application\Pagination\CursorPage;
use App\Shared\Application\Pagination\InvalidCursorException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * The cursor carries the date (UTC) and the id of the last row of the page.
 */
#[AsMessageHandler]
final readonly class ListClientConsentHistoryHandler
{
    private const string FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private ClientConsentReaderInterface $reader,
    ) {
    }

    /**
     * @return CursorPage<ConsentHistoryItemView>
     */
    public function __invoke(ListClientConsentHistory $query): CursorPage
    {
        $limit = Cursor::clampLimit($query->limit);
        $after = null === $query->cursor ? null : self::position($query->cursor);

        if ([] === $query->clientIds) {
            return new CursorPage([], null);
        }

        $items = $this->reader->historyOf(
            ClinicId::fromString($query->clinicId),
            array_values(array_unique($query->clientIds)),
            $after,
            $limit + 1,
        );

        $next = null;
        if (\count($items) > $limit) {
            $items = \array_slice($items, 0, $limit);
            $last  = $items[$limit - 1];
            $next  = Cursor::fromPosition([
                'at' => $last->at->setTimezone(new \DateTimeZone('UTC'))->format(self::FORMAT),
                'id' => $last->id,
            ])->encode();
        }

        return new CursorPage($items, $next);
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

        if (!\is_string($at) || !\is_string($id) || 1 !== preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id)) {
            throw new InvalidCursorException();
        }

        $date = \DateTimeImmutable::createFromFormat('!' . self::FORMAT, $at, new \DateTimeZone('UTC'));

        if (false === $date || $date->format(self::FORMAT) !== $at) {
            throw new InvalidCursorException();
        }

        return ['at' => $date, 'id' => $id];
    }
}
