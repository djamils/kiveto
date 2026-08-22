<?php

declare(strict_types=1);

namespace App\Shared\Application\Pdf;

/**
 * Bytes in, bytes out. The shared kernel knows nothing about the documents it
 * renders — building the HTML is the Presentation layer's business.
 */
interface PdfRendererInterface
{
    /**
     * @throws PdfRenderingFailedException
     *
     * @return string the raw PDF
     */
    public function render(PdfRenderRequest $request): string;

    /**
     * Concatenates several PDFs into one, in the order given.
     *
     * @param list<string> $documents raw PDFs
     *
     * @throws PdfRenderingFailedException
     *
     * @return string the raw PDF
     */
    public function merge(array $documents): string;
}
