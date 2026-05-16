<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Infrastructure\ImportSources\France\Dto;

final readonly class AnmvDictionaryEntry
{
    public function __construct(
        public string $type,
        public int $id,
        public string $label,
    ) {
    }
}
