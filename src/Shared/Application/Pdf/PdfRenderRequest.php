<?php

declare(strict_types=1);

namespace App\Shared\Application\Pdf;

/**
 * One HTML document to turn into a PDF.
 *
 * Margins are carried here rather than left to the stylesheet: the same HTML has
 * to come out identical whatever the renderer makes of an `@page` rule, and the
 * repeated footer is placed by the renderer inside the bottom margin.
 */
final readonly class PdfRenderRequest
{
    public function __construct(
        public string $html,
        public ?string $footerHtml = null,
        public string $marginTop = '14mm',
        public string $marginBottom = '20mm',
        public string $marginLeft = '14mm',
        public string $marginRight = '14mm',
        public bool $printBackground = true,
    ) {
        if ('' === trim($this->html)) {
            throw new \InvalidArgumentException('Cannot render an empty document.');
        }
    }
}
