<?php

declare(strict_types=1);

namespace App\Context\Procurement\Infrastructure\Adapter\Supplier\Manual;

use App\Context\Procurement\Domain\PurchaseOrder\PurchaseOrder;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderLineStatus;
use App\Context\Procurement\Domain\SupplierAccount\SupplierAccount;
use App\Shared\Domain\Storage\FileStorageInterface;

/**
 * Exports a purchase order to a generic CSV format usable with any supplier
 * that accepts a simple column-based order file.
 *
 * Columns: supplier_product_code, product_name, quantity, unit, unit_price_ht, currency, po_number
 */
final class GenericCsvExporter extends ManualExportAdapter
{
    public function __construct(FileStorageInterface $fileStorage)
    {
        parent::__construct($fileStorage);
    }

    public function identifier(): string
    {
        return 'generic_csv';
    }

    protected function exportFilename(PurchaseOrder $order): string
    {
        return \sprintf('generic-order-%s.csv', $order->orderNumber()->toString());
    }

    protected function buildExportContent(PurchaseOrder $order, SupplierAccount $account): string
    {
        $output = fopen('php://temp', 'w+');
        if (false === $output) {
            throw new \RuntimeException('Cannot open temp stream for CSV export.');
        }

        fputcsv($output, ['supplier_product_code', 'product_name', 'quantity', 'unit', 'unit_price_ht', 'currency', 'po_number'], ',', '"', '\\');

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
                $line->unitPrice()->currency()->toString(),
                $order->orderNumber()->toString(),
            ], ',', '"', '\\');
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return false !== $csv ? $csv : '';
    }
}
