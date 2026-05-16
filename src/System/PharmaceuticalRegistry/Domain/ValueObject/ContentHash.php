<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\ValueObject;

final class ContentHash
{
    private string $value;

    private function __construct(string $value)
    {
        if (1 !== preg_match('/^[0-9a-f]{64}$/', $value)) {
            throw new \InvalidArgumentException('Content hash must be a sha-256 hex string (64 chars).');
        }

        $this->value = $value;
    }

    /** @param array<mixed> $dto Pure PHP array of scalars and nested arrays — no floats, no objects. */
    public static function of(array $dto): self
    {
        self::ksortRecursive($dto);
        $json = json_encode($dto, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);

        return new self(hash('sha256', $json));
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /** @param array<mixed> $arr */
    private static function ksortRecursive(array &$arr): void
    {
        ksort($arr);

        foreach ($arr as &$value) {
            if (\is_array($value)) {
                self::ksortRecursive($value);
            }
        }
    }
}
