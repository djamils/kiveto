<?php

declare(strict_types=1);

namespace App\Tests\Support\Document;

use App\Shared\Application\Document\DocumentFooter;
use App\Shared\Application\Document\DocumentPdfRendererInterface;
use App\Shared\Application\Document\DocumentRenderingFailed;

/**
 * Records every document asked of the renderer and answers a fake PDF naming
 * its template; or fails, like a PDF service that is down.
 */
final class CapturingDocumentPdfRenderer implements DocumentPdfRendererInterface
{
    /** @var list<array{template: string, context: array<string, mixed>, footer: DocumentFooter}> */
    public array $rendered = [];

    public function __construct(private readonly bool $failing = false)
    {
    }

    public function render(string $template, array $context, DocumentFooter $footer): string
    {
        if ($this->failing) {
            throw DocumentRenderingFailed::because(new \RuntimeException('Gotenberg is down'));
        }

        $this->rendered[] = ['template' => $template, 'context' => $context, 'footer' => $footer];

        return '%PDF ' . $template;
    }

    /**
     * @return array{template: string, context: array<string, mixed>, footer: DocumentFooter}
     */
    public function last(): array
    {
        $last = $this->rendered[array_key_last($this->rendered) ?? -1] ?? null;
        \assert(null !== $last, 'Nothing was rendered.');

        return $last;
    }
}
