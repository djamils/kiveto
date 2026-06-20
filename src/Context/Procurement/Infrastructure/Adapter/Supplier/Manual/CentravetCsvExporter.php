<?php

declare(strict_types=1);

namespace App\Context\Procurement\Infrastructure\Adapter\Supplier\Manual;

use App\Context\Procurement\Domain\PurchaseOrder\PurchaseOrder;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderLineStatus;
use App\Context\Procurement\Domain\SupplierAccount\SupplierAccount;
use App\Shared\Domain\Storage\FileStorageInterface;

/**
 * Exports a purchase order to a Centravet-format CSV file.
 *
 * Column order matches Centravet's EDI import specification:
 * CODE_FOURNISSEUR, NOM_PRODUIT, QTE, UNITE, PRIX_UNITAIRE_HT, REFERENCE_COMMANDE
 */
final class CentravetCsvExporter extends ManualExportAdapter
{
    public function __construct(FileStorageInterface $fileStorage)
    {
        parent::__construct($fileStorage);
    }

    public function identifier(): string
    {
        return 'centravet_csv';
    }

    protected function exportFilename(PurchaseOrder $order): string
    {
        return \sprintf('centravet-order-%s.csv', $order->orderNumber()->toString());
    }

    protected function buildExportContent(PurchaseOrder $order, SupplierAccount $account): string
    {
        $output = fopen('php://temp', 'w+');
        if (false === $output) {
            throw new \RuntimeException('Cannot open temp stream for CSV export.');
        }

        // Centravet header row
        fputcsv($output, ['CODE_FOURNISSEUR', 'NOM_PRODUIT', 'QTE', 'UNITE', 'PRIX_UNITAIRE_HT', 'REFERENCE_COMMANDE'], ',', '"', '\\');

        foreach ($order->lines() as $line) {
            if (PurchaseOrderLineStatus::CANCELLED === $line->status()) {
                continue;
            }

            fputcsv($output, [
                $line->catalogEntryId()->toString(),
                '', // Product name is not stored on the line; a catalog lookup would be needed
                $line->orderedAmount(),
                $line->orderedUnit()->toString(),
                number_format($line->unitPrice()->minorUnits() / 100, 2, '.', ''),
                $order->orderNumber()->toString(),
            ], ',', '"', '\\');
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return false !== $csv ? $csv : '';
    }
}
