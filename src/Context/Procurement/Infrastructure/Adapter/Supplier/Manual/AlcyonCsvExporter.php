<?php

declare(strict_types=1);

namespace App\Context\Procurement\Infrastructure\Adapter\Supplier\Manual;

use App\Context\Procurement\Domain\PurchaseOrder\PurchaseOrder;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderLineStatus;
use App\Context\Procurement\Domain\SupplierAccount\SupplierAccount;
use App\Shared\Domain\Storage\FileStorageInterface;

/**
 * Exports a purchase order to an Alcyon-format CSV file.
 *
 * Alcyon-specific column ordering differs from Centravet:
 * REFERENCE_COMMANDE, CODE_ARTICLE, QUANTITE, UNITE_MESURE, PRIX_HT, CODE_CLIENT
 */
final class AlcyonCsvExporter extends ManualExportAdapter
{
    public function __construct(FileStorageInterface $fileStorage)
    {
        parent::__construct($fileStorage);
    }

    public function identifier(): string
    {
        return 'alcyon_csv';
    }

    protected function exportFilename(PurchaseOrder $order): string
    {
        return \sprintf('alcyon-order-%s.csv', $order->orderNumber()->toString());
    }

    protected function buildExportContent(PurchaseOrder $order, SupplierAccount $account): string
    {
        $output = fopen('php://temp', 'w+');
        if (false === $output) {
            throw new \RuntimeException('Cannot open temp stream for CSV export.');
        }

        // Alcyon-specific header with different column ordering than Centravet
        fputcsv($output, ['REFERENCE_COMMANDE', 'CODE_ARTICLE', 'QUANTITE', 'UNITE_MESURE', 'PRIX_HT', 'CODE_CLIENT'], ',', '"', '\\');

        foreach ($order->lines() as $line) {
            if (PurchaseOrderLineStatus::CANCELLED === $line->status()) {
                continue;
            }

            fputcsv($output, [
                $order->orderNumber()->toString(),
                $line->catalogEntryId()->toString(),
                $line->orderedAmount(),
                $line->orderedUnit()->toString(),
                number_format($line->unitPrice()->minorUnits() / 100, 2, '.', ''),
                $account->customerCode()->toString(),
            ], ',', '"', '\\');
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return false !== $csv ? $csv : '';
    }
}
