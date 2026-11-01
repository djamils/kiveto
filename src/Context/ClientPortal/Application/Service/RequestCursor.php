<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Service;

use App\Context\ClientPortal\Application\Port\RequestPosition;
use App\Shared\Application\Pagination\Cursor;
use App\Shared\Application\Pagination\InvalidCursorException;
use Symfony\Component\Uid\Uuid;

/** The opaque cursor of the request lists: the last request's `(submitted_at, id)`, UTC. */
final class RequestCursor
{
    private const string FORMAT = 'Y-m-d H:i:s';

    /** @throws InvalidCursorException for a cursor of another shape */
    public static function position(string $cursor): RequestPosition
    {
        $position = Cursor::decode($cursor)->position();
        $at       = $position['rs'] ?? null;
        $id       = $position['id'] ?? null;

        if (!\is_string($at) || !\is_string($id) || !Uuid::isValid($id)) {
            throw new InvalidCursorException();
        }

        $submittedAt = \DateTimeImmutable::createFromFormat('!' . self::FORMAT, $at, new \DateTimeZone('UTC'));

        if (false === $submittedAt || $submittedAt->format(self::FORMAT) !== $at) {
            throw new InvalidCursorException();
        }

        return new RequestPosition($submittedAt, $id);
    }

    public static function after(\DateTimeImmutable $submittedAt, string $id): string
    {
        return Cursor::fromPosition([
            'rs' => $submittedAt->setTimezone(new \DateTimeZone('UTC'))->format(self::FORMAT),
            'id' => $id,
        ])->encode();
    }
}
