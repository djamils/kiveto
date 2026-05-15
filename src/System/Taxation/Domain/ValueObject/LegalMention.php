<?php

declare(strict_types=1);

namespace App\System\Taxation\Domain\ValueObject;

final class LegalMention
{
    public function __construct(
        public readonly string $code,
        public readonly string $text,
        public readonly string $locale,
    ) {
    }
}
