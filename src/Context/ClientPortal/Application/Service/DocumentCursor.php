<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Service;

use App\Context\ClientPortal\Application\Port\DocumentPosition;
use App\Context\ClientPortal\Application\Port\ListedDocument;
use App\Shared\Application\Pagination\Cursor;
use App\Shared\Application\Pagination\InvalidCursorException;
use Symfony\Component\Uid\Uuid;

/** The cursor of the registry pages: `(issued_at, id)` of the last row, in UTC. */
final class DocumentCursor
{
    private const string FORMAT = 'Y-m-d H:i:s';

    /** @throws InvalidCursorException for a cursor of another shape */
    public static function position(string $cursor): DocumentPosition
    {
        $position = Cursor::decode($cursor)->position();
        $at       = $position['at'] ?? null;
        $id       = $position['id'] ?? null;

        if (!\is_string($at) || !\is_string($id) || !Uuid::isValid($id)) {
            throw new InvalidCursorException();
        }

        $issuedAt = \DateTimeImmutable::createFromFormat('!' . self::FORMAT, $at, new \DateTimeZone('UTC'));

        if (false === $issuedAt || $issuedAt->format(self::FORMAT) !== $at) {
            throw new InvalidCursorException();
        }

        return new DocumentPosition($issuedAt, $id);
    }

    public static function after(ListedDocument $last): string
    {
        return Cursor::fromPosition([
            'at' => $last->issuedAt->setTimezone(new \DateTimeZone('UTC'))->format(self::FORMAT),
            'id' => $last->id,
        ])->encode();
    }
}
