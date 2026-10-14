<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Procurement\Story;

use App\Context\Procurement\Application\Command\AddPurchaseOrderLine\AddPurchaseOrderLine;
use App\Context\Procurement\Application\Command\AddSupplierCatalogEntry\AddSupplierCatalogEntry;
use App\Context\Procurement\Application\Command\CreatePurchaseOrder\CreatePurchaseOrder;
use App\Context\Procurement\Application\Command\CreateSupplierAccount\CreateSupplierAccount;
use App\Context\Procurement\Application\Command\RegisterSupplier\RegisterSupplier;
use App\Context\Procurement\Application\Command\SubmitPurchaseOrder\SubmitPurchaseOrder;
use App\Fixtures\Context\Clinic\Story\ClinicDataStory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Story;

/**
 * The suppliers the demo clinics buy from, and what they sell them.
 *
 * Centravet and Alcyon are registered with a simulated integration (an order
 * « sent » lands in the simulation tables), Coveto takes its orders by file
 * (sending one exports the CSV to hand over), Paris and Lyon hold an account with
 * each, and every article their catalogue tracks is listed by one of them —
 * under the article's code and barcode, packed the way it is delivered, at the
 * price the clinic pays. Paris also has an order sent to Alcyon two days ago
 * and not delivered yet, so the stock page shows an order waiting and the
 * reception can take it over. Clinique des Calanques has no supplier at all.
 */
final class ProcurementDemoStory extends Story
{
    /**
     * Supplier code => [name, integration, adapter]. Coveto takes its orders
     * by file: sending one exports the CSV to hand over.
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    public const array SUPPLIERS = [
        'CENTRAVET' => ['Centravet', 'SIMULATION', 'simulated'],
        'ALCYON'    => ['Alcyon', 'SIMULATION', 'simulated'],
        'COVETO'    => ['Coveto', 'MANUAL_EXPORT', 'generic_csv'],
    ];

    /**
     * Article code => [supplier code, packaging unit, units per packaging, price of one packaging in cents].
     *
     * @var array<string, array{0: string, 1: string|null, 2: string|null, 3: int}>
     */
    public const array OFFERS = [
        'APOQUEL-36'     => ['CENTRAVET', 'BOX', '20', 2740],
        'BAYTRIL-10'     => ['CENTRAVET', null, null, 2410],
        'CATH-22G'       => ['ALCYON', null, null, 95],
        'CERENIA-10'     => ['CENTRAVET', null, null, 1980],
        'COLLAR-E'       => ['ALCYON', null, null, 260],
        'COMPRESSE'      => ['CENTRAVET', null, null, 420],
        'CONVENIA-80'    => ['CENTRAVET', null, null, 5800],
        'CYSTAID-PLUS'   => ['CENTRAVET', 'BOX', '30', 1190],
        'FIL-SUT-30'     => ['ALCYON', null, null, 310],
        'FOOD-HILLS-KD'  => ['CENTRAVET', 'BAG', '4', 2790],
        'FOOD-JUNIOR'    => ['CENTRAVET', 'BAG', '12', 2150],
        'FOOD-RC'        => ['CENTRAVET', 'BAG', '8', 3100],
        'FRONTLINE-CB'   => ['CENTRAVET', 'BOX', '3', 960],
        'KETAMINE-100'   => ['CENTRAVET', null, null, 2100],
        'MEDETO-1'       => ['COVETO', null, null, 2600],
        'MELOXIDYL-1'    => ['CENTRAVET', null, null, 490],
        'NARKETAN-10'    => ['COVETO', null, null, 2340],
        'OXYTETRA-200'   => ['CENTRAVET', null, null, 1720],
        'SERINGUE-5'     => ['CENTRAVET', null, null, 8],
        'SHAMPOING-DERM' => ['CENTRAVET', null, null, 840],
        'STRONGHOLD-45'  => ['CENTRAVET', 'BOX', '3', 1270],
        'SYNULOX-500'    => ['CENTRAVET', 'BOX', '10', 730],
        'TERRAMYCIN-LA'  => ['COVETO', null, null, 2170],
        'VETMEDIN-125'   => ['CENTRAVET', 'BOX', '50', 1890],
    ];

    /** What Paris ordered from Alcyon and is still waiting for: article code => packagings. */
    private const array PENDING_ORDER = ['CATH-22G' => '50', 'FIL-SUT-30' => '24'];

    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly Connection $connection,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function build(): void
    {
        $suppliers = [];

        foreach (self::SUPPLIERS as $code => [$name, $integration, $adapter]) {
            $suppliers[$code] = $this->register($code, $name, $integration, $adapter);
        }

        foreach ([ClinicDataStory::INDEPENDENT_CLINIC_ID, ClinicDataStory::GROUP_CLINIC_ID] as $clinicId) {
            foreach ($suppliers as $code => $supplierId) {
                $this->commandBus->dispatch(new CreateSupplierAccount(
                    clinicId: $clinicId,
                    supplierId: $supplierId,
                    customerCode: $code . '-' . mb_strtoupper(mb_substr(str_replace('-', '', $clinicId), -6)),
                ));
            }
        }

        // The catalogue of each supplier lists the articles under their code
        // and barcode; both clinics' catalogues share them.
        $articles = $this->articles(ClinicDataStory::INDEPENDENT_CLINIC_ID);

        foreach (self::OFFERS as $code => [$supplier, $packagingUnit, $packagingAmount, $price]) {
            if (!isset($articles[$code])) {
                continue;
            }

            $this->commandBus->dispatch(new AddSupplierCatalogEntry(
                supplierId: $suppliers[$supplier],
                productCode: $code,
                name: $articles[$code]['name'],
                gtin: $articles[$code]['gtin'],
                catalogPriceMinor: $price,
                catalogPriceCurrency: 'EUR',
                catalogPriceValidFrom: new \DateTimeImmutable('-1 year')->format('Y-m-d'),
                catalogPriceValidTo: null,
                packagingUnit: $packagingUnit,
                packagingAmount: $packagingAmount,
            ));
        }

        $this->pendingOrder(ClinicDataStory::INDEPENDENT_CLINIC_ID, $suppliers['ALCYON'], $articles);
    }

    private function register(string $code, string $name, string $integration, string $adapter): string
    {
        $this->commandBus->dispatch(new RegisterSupplier(
            name: $name,
            code: $code,
            type: 'CENTRALE',
            countryCode: 'FR',
            defaultCurrency: 'EUR',
            integrationMode: $integration,
            adapterIdentifier: $adapter,
        ));

        $id = $this->connection->fetchOne(
            'SELECT BIN_TO_UUID(id) FROM procurement__suppliers WHERE code = :code',
            ['code' => $code],
        );

        \assert(\is_string($id));

        return $id;
    }

    /**
     * @param array<string, array{id: string, name: string, gtin: string|null, unit: string}> $articles
     */
    private function pendingOrder(string $clinicId, string $supplierId, array $articles): void
    {
        $account = $this->connection->fetchOne(
            'SELECT BIN_TO_UUID(id) FROM procurement__supplier_accounts WHERE clinic_id = :clinicId AND supplier_id = :supplierId',
            ['clinicId' => Uuid::fromString($clinicId)->toBinary(), 'supplierId' => Uuid::fromString($supplierId)->toBinary()],
        );

        \assert(\is_string($account));

        $orderId = $this->commandBus->dispatch(new CreatePurchaseOrder(
            clinicId: $clinicId,
            supplierId: $supplierId,
            supplierAccountId: $account,
            deliveryAddress: [],
        ));

        \assert(\is_string($orderId));

        foreach (self::PENDING_ORDER as $code => $quantity) {
            $entry = $this->connection->fetchOne(
                'SELECT BIN_TO_UUID(id) FROM procurement__supplier_catalog_entries WHERE supplier_id = :supplierId AND supplier_product_code = :code',
                ['supplierId' => Uuid::fromString($supplierId)->toBinary(), 'code' => $code],
            );

            if (!\is_string($entry) || !isset($articles[$code])) {
                continue;
            }

            $this->commandBus->dispatch(new AddPurchaseOrderLine(
                purchaseOrderId: $orderId,
                clinicId: $clinicId,
                articleId: $articles[$code]['id'],
                catalogEntryId: $entry,
                orderedAmount: $quantity,
                orderedUnit: $articles[$code]['unit'],
                unitPriceMinor: self::OFFERS[$code][3],
                unitPriceCurrency: 'EUR',
                note: null,
            ));
        }

        $this->commandBus->dispatch(new SubmitPurchaseOrder($orderId, $clinicId));

        // Sent two days ago rather than this very second.
        $this->connection->executeStatement(
            'UPDATE procurement__purchase_orders SET submitted_at = :sent, created_at = :sent WHERE id = :id',
            ['sent' => new \DateTimeImmutable('-2 days 16:40')->format('Y-m-d H:i:s'), 'id' => Uuid::fromString($orderId)->toBinary()],
        );

        $this->entityManager->clear();
    }

    /**
     * @return array<string, array{id: string, name: string, gtin: string|null, unit: string}> keyed by code
     */
    private function articles(string $clinicId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT BIN_TO_UUID(id) AS id, code, name, gtin, unit_of_measure FROM catalog__articles WHERE tenant_id = :clinicId',
            ['clinicId' => Uuid::fromString($clinicId)->toBinary()],
        );

        $articles = [];

        foreach ($rows as $row) {
            $articles[RowAccessor::string($row, 'code')] = [
                'id'   => RowAccessor::string($row, 'id'),
                'name' => RowAccessor::string($row, 'name'),
                'gtin' => RowAccessor::nullableString($row, 'gtin'),
                'unit' => RowAccessor::string($row, 'unit_of_measure'),
            ];
        }

        return $articles;
    }
}
