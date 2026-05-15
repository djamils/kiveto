<?php

declare(strict_types=1);

namespace App\System\Taxation\Domain\ValueObject;

final class LegalMentions
{
    /** @param list<LegalMention> $items */
    private function __construct(private readonly array $items)
    {
    }

    public static function empty(): self
    {
        return new self([]);
    }

    public static function fromList(LegalMention ...$items): self
    {
        return new self(array_values($items));
    }

    public function isEmpty(): bool
    {
        return [] === $this->items;
    }

    public function count(): int
    {
        return \count($this->items);
    }

    public function byCode(string $code): ?LegalMention
    {
        foreach ($this->items as $item) {
            if ($item->code === $code) {
                return $item;
            }
        }

        return null;
    }

    /** @return list<LegalMention> */
    public function toArray(): array
    {
        return $this->items;
    }
}
