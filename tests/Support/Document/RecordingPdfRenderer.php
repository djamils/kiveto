<?php

declare(strict_types=1);

namespace App\Tests\Support\Document;

use App\Shared\Application\Pdf\PdfRendererInterface;
use App\Shared\Application\Pdf\PdfRenderingFailedException;
use App\Shared\Application\Pdf\PdfRenderRequest;

/**
 * A PDF renderer that keeps the last request and answers a fake PDF, or fails
 * like an unreachable Gotenberg.
 */
final class RecordingPdfRenderer implements PdfRendererInterface
{
    public ?PdfRenderRequest $request = null;
    public bool $fail                 = false;

    public function render(PdfRenderRequest $request): string
    {
        if ($this->fail) {
            throw PdfRenderingFailedException::unreachable('down');
        }

        $this->request = $request;

        return '%PDF';
    }

    public function merge(array $documents): string
    {
        return implode('', $documents);
    }
}
