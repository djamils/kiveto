<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Document;

use App\Context\ClientPortal\Application\Port\BookletRendererInterface;
use App\Context\ClientPortal\Application\Port\VaccinationBooklet;
use App\Shared\Application\Document\DocumentFooter;
use App\Shared\Application\Document\DocumentPdfRendererInterface;

/** The booklet through the shared document shell (letterhead, page footer). */
final readonly class TwigVaccinationBookletRenderer implements BookletRendererInterface
{
    public const string TEMPLATE = 'portal/document/vaccination_booklet.html.twig';

    public function __construct(private DocumentPdfRendererInterface $documents)
    {
    }

    public function render(VaccinationBooklet $booklet): string
    {
        return $this->documents->render(
            self::TEMPLATE,
            ['booklet' => $booklet],
            new DocumentFooter($booklet->clinic, 'Carnet de vaccination', $booklet->pet->name),
        );
    }
}
