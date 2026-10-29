<?php

declare(strict_types=1);

namespace App\Shared\Application\Pagination;

/**
 * Opaque keyset-pagination cursor: the position of the last item of a page,
 * serialised as base64url (no padding) JSON `{"v":1,"p":{...}}`.
 *
 * The cursor is not signed: it only carries the sort keys of the last row seen,
 * and every listing query applies its own authorisation filters, so a forged
 * position can only skip within what the caller may already list. Anything that
 * does not decode to the expected shape is rejected with InvalidCursorException.
 */
final class Cursor
{
    public const int DEFAULT_LIMIT = 20;

    public const int MAX_LIMIT = 50;

    private const int VERSION = 1;

    /** @var non-empty-array<string, string|int> */
    private readonly array $position;

    /**
     * @param non-empty-array<string, string|int> $position
     */
    private function __construct(array $position)
    {
        $this->position = $position;
    }

    /**
     * @param array<string, string|int> $position the sort keys of the last item of the page
     */
    public static function fromPosition(array $position): self
    {
        if ([] === $position) {
            throw new \InvalidArgumentException('A cursor position cannot be empty.');
        }

        return new self($position);
    }

    /**
     * @throws InvalidCursorException
     */
    public static function decode(string $encoded): self
    {
        if (1 !== preg_match('/^[A-Za-z0-9_-]+\z/', $encoded)) {
            throw new InvalidCursorException();
        }

        $json = base64_decode(strtr($encoded, '-_', '+/'), true);

        try {
            $payload = json_decode((string) $json, true, 4, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new InvalidCursorException();
        }

        if (!\is_array($payload) || self::VERSION !== ($payload['v'] ?? null) || !\is_array($payload['p'] ?? null)) {
            throw new InvalidCursorException();
        }

        $position = [];
        foreach ($payload['p'] as $key => $value) {
            if (!\is_string($key) || (!\is_string($value) && !\is_int($value))) {
                throw new InvalidCursorException();
            }

            $position[$key] = $value;
        }

        if ([] === $position) {
            throw new InvalidCursorException();
        }

        return new self($position);
    }

    /**
     * Clamps a requested page size: null → DEFAULT_LIMIT, above MAX_LIMIT →
     * MAX_LIMIT, below 1 → 1 (a page always holds at least one item; clients
     * asking for zero or a negative size get the smallest valid page rather
     * than an error).
     */
    public static function clampLimit(?int $limit): int
    {
        if (null === $limit) {
            return self::DEFAULT_LIMIT;
        }

        return max(1, min(self::MAX_LIMIT, $limit));
    }

    public function encode(): string
    {
        $json = json_encode(['v' => self::VERSION, 'p' => $this->position], \JSON_THROW_ON_ERROR);

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    /**
     * @return non-empty-array<string, string|int>
     */
    public function position(): array
    {
        return $this->position;
    }
}
