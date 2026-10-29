<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Infrastructure\Document;

use App\Context\Billing\Application\Port\InvoicePaymentDetails;
use App\Context\Billing\Application\Query\GetInvoiceDetails\InvoiceDetails;
use App\Context\Consultation\Application\Port\ReportOwner;
use App\Context\Consultation\Application\Port\ReportPatient;
use App\Context\Consultation\Application\Port\ReportPractitioner;
use App\Context\Consultation\Application\Query\GetConsultationDetails\ConsultationDetailsDTO;
use App\Shared\Application\Document\DocumentClinic;
use App\Shared\Application\Document\DocumentFooter;
use App\Shared\Application\Document\DocumentLetterhead;
use App\Shared\Infrastructure\Document\TwigGotenbergDocumentPdfRenderer;
use App\Tests\Support\Document\RecordingPdfRenderer;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

/**
 * The real document templates read the values the bounded-context renderers
 * hand them (DocumentClinic, payment details, report snapshots): letterhead,
 * IBAN and footer come out on paper. Gotenberg is replaced by a recorder.
 */
final class DocumentTemplatesTest extends KernelTestCase
{
    public function testAnInvoicePrintsTheLetterheadThePaymentDetailsAndTheFooter(): void
    {
        $pdf  = new RecordingPdfRenderer();
        $html = $this->render($pdf, 'clinic/document/invoice.html.twig', [
            'invoice' => self::invoice(),
            'billing' => new InvoicePaymentDetails('FR76 3000 6000 0112', 'AGRIFRPP', false, 'Règlement à réception.'),
        ], new DocumentFooter(self::clinic(), 'Facture · FA-2026-0814', 'Sophie Garnier'));

        self::assertStringContainsString('SELARL Clinique Montmartre', $html);
        self::assertStringContainsString('SIRET 812 456 903 00028', $html);
        self::assertStringContainsString('IBAN FR76 3000 6000 0112', $html);
        self::assertStringContainsString('Règlement à réception.', $html);
        self::assertStringContainsString('SELARL au capital de 30 000 €', $html);
        self::assertStringContainsString('Facture · FA-2026-0814', (string) $pdf->request?->footerHtml);
        self::assertStringContainsString('Clinique Montmartre · 18 rue des Abbesses, 75018 Paris', (string) $pdf->request?->footerHtml);
    }

    public function testAReportPrintsThePatientTheOwnerAndTheSigningVeterinarian(): void
    {
        $html = $this->render(new RecordingPdfRenderer(), 'clinic/document/report.html.twig', [
            'consultation' => self::consultation(),
            'patient'      => new ReportPatient('Milo', 'Chien', 'Labrador', '♂', true, '2020-05-01', '6 ans', '250269812345678'),
            'owner'        => new ReportOwner('c-1', 'Sophie Garnier', '+33612345678', 'sophie@example.fr', '18 rue des Abbesses 75018 Paris'),
            'practitioner' => new ReportPractitioner('Claire Lambert', 'DR'),
            'startedAt'    => new \DateTimeImmutable('2026-10-22 08:30:00', new \DateTimeZone('UTC')),
            'closedAt'     => new \DateTimeImmutable('2026-10-22 09:00:00', new \DateTimeZone('UTC')),
        ], new DocumentFooter(self::clinic(), 'Compte rendu de consultation', 'Milo / Sophie Garnier'));

        self::assertStringContainsString('Dr Claire Lambert', $html);
        self::assertStringContainsString('Sophie Garnier', $html);
        self::assertStringContainsString('sophie@example.fr', $html);
        self::assertStringContainsString('Chien, Labrador', $html);
        self::assertStringContainsString('Mâle, stérilisé', $html);
        self::assertStringContainsString('250269812345678 (transpondeur)', $html);
        self::assertStringContainsString('Fait à Paris', $html);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function render(RecordingPdfRenderer $pdf, string $template, array $context, DocumentFooter $footer): string
    {
        $twig = self::getContainer()->get('twig');
        \assert($twig instanceof Environment);

        new TwigGotenbergDocumentPdfRenderer($twig, $pdf)->render($template, $context, $footer);

        return (string) $pdf->request?->html;
    }

    private static function clinic(): DocumentClinic
    {
        return new DocumentClinic('Clinique Montmartre', new DocumentLetterhead(
            legalName: 'SELARL Clinique Montmartre',
            addressLines: ['18 rue des Abbesses', '75018 Paris'],
            city: 'Paris',
            phone: '+33142641800',
            email: 'contact@montmartre.vet',
            website: null,
            formattedSiret: '812 456 903 00028',
            vatNumber: 'FR20812456903',
            ordinalNumber: '21284',
            ordinalCouncil: 'Île-de-France',
            legalSentence: 'SELARL au capital de 30 000 €',
            liabilitySentence: null,
        ), '#4f86c6');
    }

    private static function invoice(): InvoiceDetails
    {
        return new InvoiceDetails(
            id: '01960000-0000-7000-8000-0000000b0001',
            clinicId: '01960000-0000-7000-8000-000000000001',
            number: 'FA-2026-0814',
            status: 'ISSUED',
            statusLabel: 'Émise',
            admissionId: null,
            payerClientId: null,
            payerName: 'Sophie Garnier',
            payerAddressLines: ['Paris'],
            lines: [],
            payments: [],
            taxBreakdown: [],
            currency: 'EUR',
            totalNetBeforeDiscountMinorUnits: 7392,
            discountMinorUnits: 0,
            discountKind: null,
            discountValue: null,
            discountReason: null,
            totalNetMinorUnits: 7392,
            totalTaxMinorUnits: 1478,
            totalGrossMinorUnits: 8870,
            amountPaidMinorUnits: 0,
            balanceDueMinorUnits: 8870,
            notes: null,
            createdAt: new \DateTimeImmutable('2026-10-22 10:00:00'),
            issuedAt: new \DateTimeImmutable('2026-10-22 10:00:00'),
            paidAt: null,
            cancelledAt: null,
            cancellationReason: null,
        );
    }

    private static function consultation(): ConsultationDetailsDTO
    {
        return new ConsultationDetailsDTO(
            consultationId: '01960000-0000-7000-8000-0000000c0001',
            clinicId: '01960000-0000-7000-8000-000000000001',
            practitionerUserId: '01960000-0000-7000-8000-0000000c0002',
            status: 'CLOSED',
            appointmentId: null,
            admissionId: '01960000-0000-7000-8000-0000000c0003',
            patientId: '01960000-0000-7000-8000-0000000c0004',
            chiefComplaint: 'Boiterie',
            vitals: null,
            notes: [],
            acts: [],
            summary: null,
            startedAtUtc: '2026-10-22 08:30:00',
            closedAtUtc: '2026-10-22 09:00:00',
            subjectiveText: null,
            objectiveObservations: null,
            teamMemo: null,
            motifs: [],
            typedVitals: [],
            examSystems: [],
            diagnoses: [],
            planActions: [],
            prescriptionLines: [],
            billingLines: [],
            totals: ['totalHtMinorUnits' => 0, 'totalTvaMinorUnits' => 0, 'totalTtcMinorUnits' => 0, 'currency' => 'EUR'],
        );
    }
}
