<?php

declare(strict_types=1);

namespace App\Context\Procurement\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Procurement\Application\Port\OrderBookReadRepositoryInterface;
use App\Context\Procurement\Application\Query\OrderBook\OpenPurchaseOrder;
use App\Context\Procurement\Application\Query\OrderBook\OpenPurchaseOrderLine;
use App\Context\Procurement\Application\Query\OrderBook\SupplyOffer;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DbalOrderBookReadRepository implements OrderBookReadRepositoryInterface
{
    /** @var list<string> */
    private const array OPEN = ['DRAFT', 'SUBMITTING', 'SUBMITTED', 'CONFIRMED', 'PARTIALLY_RECEIVED', 'SEND_FAILED'];

    public function __construct(
        private Connection $connection,
        private ClockInterface $clock,
    ) {
    }

    public function supplyOffers(string $clinicId, array $gtins, array $productCodes): array
    {
        $match  = [];
        $params = [
            'clinicId' => Uuid::fromString($clinicId)->toBinary(),
            'now'      => $this->clock->now()->format('Y-m-d H:i:s'),
        ];
        $types = [];

        if ([] !== $gtins) {
            $match[]         = 'e.gtin IN (:gtins)';
            $params['gtins'] = $gtins;
            $types['gtins']  = ArrayParameterType::STRING;
        }

        if ([] !== $productCodes) {
            $match[]         = 'e.supplier_product_code IN (:codes)';
            $params['codes'] = $productCodes;
            $types['codes']  = ArrayParameterType::STRING;
        }

        if ([] === $match) {
            return [];
        }

        // The clinic's negotiated price wins over the catalogue's while it
        // holds; the catalogue's own validity is the supplier's business.
        $rows = $this->connection->fetchAllAssociative(
            \sprintf(
                "SELECT BIN_TO_UUID(s.id) AS supplier_id, s.name AS supplier_name, BIN_TO_UUID(a.id) AS account_id,
                        BIN_TO_UUID(e.id) AS entry_id, e.supplier_product_code, e.gtin, e.name,
                        COALESCE(p.amount_minor, e.catalog_price_minor) AS price_minor,
                        COALESCE(p.amount_currency, e.catalog_price_currency) AS currency,
                        e.packaging_unit, e.packaging_amount
                 FROM procurement__supplier_catalog_entries e
                 JOIN procurement__suppliers s ON s.id = e.supplier_id AND s.status = 'ACTIVE'
                 JOIN procurement__supplier_accounts a ON a.supplier_id = s.id AND a.clinic_id = :clinicId AND a.status = 'ACTIVE'
                 LEFT JOIN procurement__supplier_pricing p ON p.supplier_catalog_entry_id = e.id AND p.clinic_id = :clinicId
                       AND (p.expires_at IS NULL OR p.expires_at > :now)
                 WHERE e.status = 'ACTIVE' AND (%s)
                 ORDER BY s.name ASC, e.supplier_product_code ASC",
                implode(' OR ', $match),
            ),
            $params,
            $types,
        );

        return array_map(static fn (array $row): SupplyOffer => new SupplyOffer(
            supplierId: RowAccessor::string($row, 'supplier_id'),
            supplierName: RowAccessor::string($row, 'supplier_name'),
            supplierAccountId: RowAccessor::string($row, 'account_id'),
            catalogEntryId: RowAccessor::string($row, 'entry_id'),
            productCode: RowAccessor::string($row, 'supplier_product_code'),
            gtin: RowAccessor::nullableString($row, 'gtin'),
            name: RowAccessor::string($row, 'name'),
            priceMinor: RowAccessor::int($row, 'price_minor'),
            currency: RowAccessor::string($row, 'currency'),
            packagingUnit: RowAccessor::nullableString($row, 'packaging_unit'),
            packagingAmount: RowAccessor::nullableString($row, 'packaging_amount'),
        ), $rows);
    }

    public function openOrders(string $clinicId): array
    {
        $orders = $this->connection->fetchAllAssociative(
            'SELECT BIN_TO_UUID(o.id) AS id, o.order_number, BIN_TO_UUID(o.supplier_id) AS supplier_id, s.name AS supplier_name,
                    BIN_TO_UUID(o.supplier_account_id) AS account_id, o.status, o.currency, o.submitted_at, o.created_at,
                    o.external_reference_value, s.integration_mode
             FROM procurement__purchase_orders o
             JOIN procurement__suppliers s ON s.id = o.supplier_id
             WHERE o.clinic_id = :clinicId AND o.status IN (:open)
             ORDER BY o.created_at ASC, o.order_number ASC',
            ['clinicId' => Uuid::fromString($clinicId)->toBinary(), 'open' => self::OPEN],
            ['open' => ArrayParameterType::STRING],
        );

        if ([] === $orders) {
            return [];
        }

        $lines = $this->connection->fetchAllAssociative(
            'SELECT BIN_TO_UUID(l.id) AS id, BIN_TO_UUID(l.purchase_order_id) AS order_id, BIN_TO_UUID(l.article_id) AS article_id,
                    BIN_TO_UUID(l.catalog_entry_id) AS entry_id, l.ordered_amount, l.ordered_unit, l.received_amount,
                    l.unit_price_minor, l.status
             FROM procurement__purchase_order_lines l
             WHERE l.purchase_order_id IN (:orders)
             ORDER BY l.id ASC',
            ['orders' => array_map(static fn (array $row): string => Uuid::fromString(RowAccessor::string($row, 'id'))->toBinary(), $orders)],
            ['orders' => ArrayParameterType::BINARY],
        );

        $byOrder = [];

        foreach ($lines as $row) {
            $byOrder[RowAccessor::string($row, 'order_id')][] = new OpenPurchaseOrderLine(
                id: RowAccessor::string($row, 'id'),
                articleId: RowAccessor::string($row, 'article_id'),
                catalogEntryId: RowAccessor::string($row, 'entry_id'),
                orderedAmount: RowAccessor::string($row, 'ordered_amount'),
                orderedUnit: RowAccessor::string($row, 'ordered_unit'),
                receivedAmount: RowAccessor::string($row, 'received_amount'),
                unitPriceMinor: RowAccessor::int($row, 'unit_price_minor'),
                status: RowAccessor::string($row, 'status'),
            );
        }

        return array_map(static function (array $row) use ($byOrder): OpenPurchaseOrder {
            $id = RowAccessor::string($row, 'id');

            return new OpenPurchaseOrder(
                id: $id,
                orderNumber: RowAccessor::string($row, 'order_number'),
                supplierId: RowAccessor::string($row, 'supplier_id'),
                supplierName: RowAccessor::string($row, 'supplier_name'),
                supplierAccountId: RowAccessor::string($row, 'account_id'),
                status: RowAccessor::string($row, 'status'),
                currency: RowAccessor::string($row, 'currency'),
                submittedAt: RowAccessor::nullableString($row, 'submitted_at'),
                createdAt: RowAccessor::string($row, 'created_at'),
                lines: $byOrder[$id] ?? [],
                exportFile: 'MANUAL_EXPORT' === RowAccessor::string($row, 'integration_mode')
                    ? RowAccessor::nullableString($row, 'external_reference_value')
                    : null,
            );
        }, $orders);
    }
}
