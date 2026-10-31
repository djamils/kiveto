<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

use App\Shared\Application\Document\DocumentRenderingFailed;

/** Turns a vaccination booklet into a PDF. */
interface BookletRendererInterface
{
    /**
     * @throws DocumentRenderingFailed when the PDF service is down or refuses the document
     *
     * @return string the PDF content
     */
    public function render(VaccinationBooklet $booklet): string;
}
