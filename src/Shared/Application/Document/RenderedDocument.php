<?php

declare(strict_types=1);

namespace App\Shared\Application\Document;

/**
 * A rendered PDF and the file name it is saved or attached under
 * ("FA-2026-0814.pdf").
 */
final readonly class RenderedDocument
{
    public function __construct(
        public string $filename,
        public string $content,
    ) {
    }
}
