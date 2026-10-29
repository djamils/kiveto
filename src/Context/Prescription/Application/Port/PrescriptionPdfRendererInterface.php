<?php

declare(strict_types=1);

namespace App\Context\Prescription\Application\Port;

use App\Shared\Application\Document\DocumentRenderingFailed;
use App\Shared\Application\Document\RenderedDocument;

/**
 * The PDF of an ordonnance, as printed and as attached to an e-mail.
 */
interface PrescriptionPdfRendererInterface
{
    /**
     * @throws \DomainException        when the prescription is unknown to this clinic
     * @throws DocumentRenderingFailed when the PDF service is down or refuses it
     */
    public function prescription(string $clinicId, string $prescriptionId): RenderedDocument;
}
