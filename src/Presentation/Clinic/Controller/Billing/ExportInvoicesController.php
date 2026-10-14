<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Billing;

use App\Presentation\Clinic\ViewModel\Billing\BillingPageBuilder;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The bulk bar's « Exporter »: the selected invoices (`ids[]`) and credit
 * notes (`av[]`) as a CSV the spreadsheet opens.
 */
#[Route(path: '/invoices/export.csv', name: 'clinic_invoices_export', methods: ['GET'])]
final class ExportInvoicesController extends AbstractController
{
    private const int MAX_DOCUMENTS = 500;

    private const array STATUS_LABELS = [
        'DRAFT'          => 'Brouillon',
        'ISSUED'         => 'Émise · à régler',
        'PARTIALLY_PAID' => 'Émise · réglée en partie',
        'PAID'           => 'Émise · soldée',
        'CREDITED'       => 'Annulée par avoir',
        'CANCELLED'      => 'Annulée',
        'CREDIT_NOTE'    => 'Avoir',
    ];

    public function __construct(
        private readonly BillingPageBuilder $pages,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $clinicId = $currentClinicId->toString();
        $out      = fopen('php://temp', 'r+');
        \assert(false !== $out);

        // A BOM so that the spreadsheet reads the accents as UTF-8.
        fwrite($out, "\u{FEFF}");
        fputcsv($out, ['Numéro', 'Type', 'Date', 'Client', 'Animal', 'Objet', 'Praticien', 'Statut', 'Montant TTC', 'Réglé', 'Reste dû'], ';', '"', '');

        $sheets = [];

        foreach (\array_slice(InvoicesSelectionPdfController::ids($request, 'ids'), 0, self::MAX_DOCUMENTS) as $id) {
            $sheets[] = $this->pages->invoiceSheet($clinicId, $id);
        }

        foreach (\array_slice(InvoicesSelectionPdfController::ids($request, 'av'), 0, self::MAX_DOCUMENTS) as $id) {
            $sheets[] = $this->pages->creditNoteSheet($clinicId, $id);
        }

        foreach (array_filter($sheets) as $sheet) {
            $note = 'av' === $sheet['kind'];
            $sign = $note ? -1 : 1;

            fputcsv($out, [
                self::text($sheet['num'] ?? ''),
                $note ? 'Avoir' : 'Facture',
                implode('/', array_reverse(explode('-', self::text($sheet['d'])))) . ' ' . self::text($sheet['h']),
                self::text(\is_array($sheet['cli']) ? ($sheet['cli']['n'] ?? '') : ''),
                implode(', ', array_map(static fn (mixed $a): string => \is_array($a) ? self::text($a['n'] ?? '') : '', \is_array($sheet['anis']) ? $sheet['anis'] : [])),
                self::text($sheet['objet']),
                \is_array($sheet['vet']) ? self::text($sheet['vet']['n'] ?? '') : '',
                self::STATUS_LABELS[self::text($sheet['raw'])] ?? '',
                self::amount($sign * self::int($sheet['tot'])),
                $note ? '' : self::amount(self::int($sheet['paye'] ?? 0)),
                $note ? '' : self::amount(self::int($sheet['du'] ?? 0)),
            ], ';', '"', '');
        }

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return new Response(false !== $csv ? $csv : '', Response::HTTP_OK, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => \sprintf('attachment; filename="factures-%s.csv"', $this->clock->now()->setTimezone($this->pages->timeZone($clinicId))->format('Y-m-d')),
            'Cache-Control'       => 'private, no-store',
        ]);
    }

    private static function text(mixed $value): string
    {
        return \is_string($value) ? $value : '';
    }

    private static function int(mixed $value): int
    {
        return \is_int($value) ? $value : 0;
    }

    private static function amount(int $minorUnits): string
    {
        return number_format($minorUnits / 100, 2, ',', '');
    }
}
