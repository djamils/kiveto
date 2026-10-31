<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\ClientPortal\Infrastructure\Document;

use App\Context\ClientPortal\Application\Port\PetIdentity;
use App\Context\ClientPortal\Application\Port\VaccinationBooklet;
use App\Context\ClientPortal\Application\Port\VaccinationRecord;
use App\Context\ClientPortal\Infrastructure\Document\TwigVaccinationBookletRenderer;
use App\Shared\Application\Document\DocumentClinic;
use App\Shared\Infrastructure\Document\TwigGotenbergDocumentPdfRenderer;
use App\Tests\Support\Document\RecordingPdfRenderer;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

/** The real booklet template, Gotenberg replaced by a recorder. */
final class TwigVaccinationBookletRendererTest extends KernelTestCase
{
    public function testTheBookletPrintsTheAnimalAndItsVaccinationsWithoutShiftingTheirDays(): void
    {
        $pdf     = new RecordingPdfRenderer();
        $noumea  = new \DateTimeZone('Pacific/Noumea');
        $booklet = new VaccinationBooklet(
            new DocumentClinic('Clinique des Lilas'),
            new PetIdentity('Moka', 'cat', null, true, 'female', new \DateTimeImmutable('2020-05-01', $noumea), '250269812345678'),
            [
                new VaccinationRecord('r2', 'Typhus', new \DateTimeImmutable('2026-10-01', $noumea), null, null),
                new VaccinationRecord('r1', 'Rage', new \DateTimeImmutable('2025-11-10', $noumea), 'LOT-42', new \DateTimeImmutable('2026-11-10', $noumea)),
            ],
        );

        self::assertSame('%PDF', $this->renderer($pdf)->render($booklet));

        $html = (string) $pdf->request?->html;
        self::assertStringContainsString('Carnet de vaccination', $html);
        self::assertStringContainsString('Clinique des Lilas', $html);
        self::assertStringContainsString('Chat, croisé', $html);
        self::assertStringContainsString('Femelle', $html);
        self::assertStringContainsString('01/05/2020', $html);
        self::assertStringContainsString('250269812345678 (transpondeur)', $html);
        self::assertStringContainsString('10/11/2025', $html);
        self::assertStringContainsString('LOT-42', $html);
        self::assertStringContainsString('10/11/2026', $html);
        self::assertStringContainsString('2 vaccinations enregistrées', $html);
        self::assertStringNotContainsString('Aucune vaccination enregistrée', $html);
        self::assertStringContainsString('Carnet de vaccination', (string) $pdf->request?->footerHtml);
        self::assertStringContainsString('Moka', (string) $pdf->request?->footerHtml);
    }

    public function testABookletWithoutVaccinationSaysSo(): void
    {
        $pdf = new RecordingPdfRenderer();

        $this->renderer($pdf)->render(new VaccinationBooklet(null, new PetIdentity('Rex', 'dog', 'Labrador', false, 'male', null, null), []));

        $html = (string) $pdf->request?->html;
        self::assertStringContainsString('Aucune vaccination enregistrée', $html);
        self::assertStringContainsString('Chien, Labrador', $html);
        self::assertStringContainsString('Non renseignée', $html);
    }

    private function renderer(RecordingPdfRenderer $pdf): TwigVaccinationBookletRenderer
    {
        $twig = self::getContainer()->get('twig');
        \assert($twig instanceof Environment);

        return new TwigVaccinationBookletRenderer(new TwigGotenbergDocumentPdfRenderer($twig, $pdf));
    }
}
