<?php

declare(strict_types=1);

namespace App\Context\Procurement\Infrastructure\Adapter\Supplier\Simulation;

use App\Context\Procurement\Application\Dto\CatalogEntryData;
use App\Context\Procurement\Application\Dto\DeliveryNoteData;
use App\Context\Procurement\Application\Dto\SendOrderResult;
use App\Context\Procurement\Application\Port\SupplierIntegrationAdapterInterface;
use App\Context\Procurement\Domain\PurchaseOrder\PurchaseOrder;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\ExternalReference;
use App\Context\Procurement\Domain\Supplier\Supplier;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierIntegrationMode;
use App\Context\Procurement\Domain\SupplierAccount\SupplierAccount;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Yaml\Yaml;

/**
 * Simulated supplier adapter for development and demo environments.
 *
 * Writes orders and deliveries to raw DBAL tables (outside Doctrine EM):
 *   - procurement__simulated_orders
 *   - procurement__simulated_deliveries
 *
 * Reads simulated catalog data from YAML files under resources/simulated-catalogs/.
 */
final class SimulatedSupplierAdapter implements SupplierIntegrationAdapterInterface
{
    private SimulationProfileConfig $profileConfig;

    public function __construct(
        private readonly Connection $connection,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        #[Autowire('%procurement.simulation.profile%')]
        string $profile = SimulationProfileConfig::DEMO_FAST,
    ) {
        $this->profileConfig = SimulationProfileConfig::fromString($profile);
    }

    public function identifier(): string
    {
        return 'simulated';
    }

    public function mode(): SupplierIntegrationMode
    {
        return SupplierIntegrationMode::SIMULATION;
    }

    public function sendOrder(PurchaseOrder $order, SupplierAccount $account): SendOrderResult
    {
        $externalRef = 'SIM-' . Uuid::v7()->toRfc4122();
        $now         = new \DateTimeImmutable();
        $orderId     = Uuid::v7()->toBinary();

        // Insert the simulated order record
        $this->connection->executeStatement(
            'INSERT INTO procurement__simulated_orders
             (id, purchase_order_id, external_reference, supplier_id, profile, delivery_delay_seconds, created_at)
             VALUES (:id, :poId, :externalRef, :supplierId, :profile, :delay, :createdAt)',
            [
                'id'          => $orderId,
                'poId'        => Uuid::fromString($order->id()->toString())->toBinary(),
                'externalRef' => $externalRef,
                'supplierId'  => Uuid::fromString($order->supplierId()->toString())->toBinary(),
                'profile'     => $this->profileConfig->profile,
                'delay'       => $this->profileConfig->deliveryDelaySeconds,
                'createdAt'   => $now->format('Y-m-d H:i:s'),
            ],
        );

        // For DEMO_FAST and DEV_INSTANT profiles, delivery is immediately available
        if (\in_array($this->profileConfig->profile, [SimulationProfileConfig::DEMO_FAST, SimulationProfileConfig::DEV_INSTANT], true)) {
            $availableAt = $now;
        } else {
            $availableAt = $now->modify(\sprintf('+%d seconds', $this->profileConfig->deliveryDelaySeconds));
        }

        // Build delivery payload from purchase order lines
        $lines = array_map(static function ($line): array {
            return [
                'articleCode' => $line->articleId()->toString(),
                'qty'         => $line->orderedAmount(),
                'unit'        => $line->orderedUnit()->toString(),
                'lotNumber'   => null,
                'expiryDate'  => null,
                'actualPrice' => null,
            ];
        }, $order->lines());

        $dnr = 'SIM-DNR-' . Uuid::v7()->toRfc4122();

        // Insert the simulated delivery record (fetched_at is NULL until polled)
        $this->connection->executeStatement(
            'INSERT INTO procurement__simulated_deliveries
             (id, simulated_order_id, purchase_order_id, delivery_note_reference, payload_json, available_at, fetched_at)
             VALUES (:id, :soId, :poId, :dnr, :payload, :availableAt, NULL)',
            [
                'id'          => Uuid::v7()->toBinary(),
                'soId'        => $orderId,
                'poId'        => Uuid::fromString($order->id()->toString())->toBinary(),
                'dnr'         => $dnr,
                'payload'     => json_encode($lines, \JSON_THROW_ON_ERROR),
                'availableAt' => $availableAt->format('Y-m-d H:i:s'),
            ],
        );

        return SendOrderResult::success(
            ref: ExternalReference::create($externalRef, 'simulated'),
            sentAt: $now,
        );
    }

    /**
     * @return list<DeliveryNoteData>
     */
    public function fetchDeliveries(
        Supplier $supplier,
        SupplierAccount $account,
        \DateTimeImmutable $since,
        \DateTimeImmutable $until,
    ): array {
        // Fetch deliveries that are available and not yet fetched for this supplier
        $rows = $this->connection->fetchAllAssociative(
            'SELECT sd.*
             FROM procurement__simulated_deliveries sd
             INNER JOIN procurement__simulated_orders so ON sd.simulated_order_id = so.id
             WHERE so.supplier_id = :supplierId
               AND sd.available_at <= :until
               AND sd.fetched_at IS NULL',
            [
                'supplierId' => Uuid::fromString($supplier->id()->toString())->toBinary(),
                'until'      => $until->format('Y-m-d H:i:s'),
            ],
        );

        if ([] === $rows) {
            return [];
        }

        $deliveries = [];
        $ids        = [];

        foreach ($rows as $row) {
            // DBAL returns array<string, mixed>; assert strings before use
            \assert(\is_string($row['id']));
            \assert(\is_string($row['payload_json']));
            \assert(\is_string($row['delivery_note_reference']));
            $ids[] = $row['id'];

            /** @var list<array{articleCode: string, qty: string, unit: string, lotNumber: string|null, expiryDate: string|null, actualPrice: int|null}> $lines */
            $lines = json_decode($row['payload_json'], true, 512, \JSON_THROW_ON_ERROR);

            // Retrieve the external reference from the associated simulated order
            /** @var array{external_reference: string}|false $orderRow */
            $orderRow = $this->connection->fetchAssociative(
                'SELECT external_reference FROM procurement__simulated_orders WHERE id = :id',
                ['id' => $row['simulated_order_id']],
            );

            $deliveries[] = new DeliveryNoteData(
                deliveryNoteReference: $row['delivery_note_reference'],
                purchaseOrderExternalReference: false !== $orderRow ? $orderRow['external_reference'] : '',
                lines: $lines,
            );
        }

        // Mark all fetched deliveries as fetched (idempotent guard)
        foreach ($ids as $id) {
            $this->connection->executeStatement(
                'UPDATE procurement__simulated_deliveries SET fetched_at = :fetchedAt WHERE id = :id',
                [
                    'fetchedAt' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                    'id'        => $id,
                ],
            );
        }

        return $deliveries;
    }

    public function supportsAsyncDeliveryPolling(): bool
    {
        return true;
    }

    public function supportsCatalogImport(): bool
    {
        return true;
    }

    /**
     * @return list<CatalogEntryData>
     */
    public function fetchCatalog(Supplier $supplier): array
    {
        $supplierCode = mb_strtolower($supplier->code()->toString());
        $filePath     = $this->projectDir . '/resources/simulated-catalogs/' . $supplierCode . '.yaml';

        if (!file_exists($filePath)) {
            return [];
        }

        /** @var array{products?: list<array{code?: string, name?: string, gtin?: string, price_minor?: int, currency?: string, unit?: string, packaging_amount?: string}>} $data */
        $data    = Yaml::parseFile($filePath);
        $entries = [];

        foreach ($data['products'] ?? [] as $product) {
            $entries[] = new CatalogEntryData(
                supplierProductCode: (string) ($product['code'] ?? ''),
                name: (string) ($product['name'] ?? ''),
                gtin: isset($product['gtin']) ? (string) $product['gtin'] : null,
                priceMinor: (int) ($product['price_minor'] ?? 0),
                currency: (string) ($product['currency'] ?? 'EUR'),
                unit: isset($product['unit']) ? (string) $product['unit'] : null,
                packagingAmount: isset($product['packaging_amount']) ? (string) $product['packaging_amount'] : null,
            );
        }

        return $entries;
    }
}
