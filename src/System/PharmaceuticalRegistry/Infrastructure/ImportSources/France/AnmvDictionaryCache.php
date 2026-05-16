<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Infrastructure\ImportSources\France;

use App\System\PharmaceuticalRegistry\Domain\Exception\UnknownAnmvCodeException;

final class AnmvDictionaryCache
{
    /** @param array<string, array<int, string>> $data section-name → [code → label] */
    public function __construct(private readonly array $data)
    {
    }

    public function getLabel(string $section, int $code): string
    {
        return $this->data[$section][$code] ?? throw new UnknownAnmvCodeException($code);
    }

    public function hasLabel(string $section, int $code): bool
    {
        return isset($this->data[$section][$code]);
    }
}
