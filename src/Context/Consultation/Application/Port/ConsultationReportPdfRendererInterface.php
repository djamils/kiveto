<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Port;

use App\Shared\Application\Document\DocumentRenderingFailed;
use App\Shared\Application\Document\RenderedDocument;

/**
 * The PDF of a consultation report, as printed and as attached to an e-mail.
 */
interface ConsultationReportPdfRendererInterface
{
    /**
     * @throws \DomainException        when the consultation is unknown to this clinic
     * @throws DocumentRenderingFailed when the PDF service is down or refuses it
     */
    public function report(string $clinicId, string $consultationId): RenderedDocument;
}
