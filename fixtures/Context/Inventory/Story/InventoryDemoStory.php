<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Inventory\Story;

use App\Context\Inventory\Application\Command\ChangeStockThreshold\ChangeStockThreshold;
use App\Context\Inventory\Application\Command\ConsumeStock\ConsumeStock;
use App\Context\Inventory\Application\Command\ExpireOutdatedLots\ExpireOutdatedLots;
use App\Context\Inventory\Application\Command\MarkLotAsRecalled\MarkLotAsRecalled;
use App\Context\Inventory\Application\Command\OpenStockItem\OpenStockItem;
use App\Context\Inventory\Application\Command\ReceiveStock\ReceiveStock;
use App\Fixtures\Context\Clinic\Story\ClinicDataStory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Story;

/**
 * Demo stock for both clinics, built on the articles their catalogue actually
 * tracks.
 *
 * Every state the stock page can show is represented: comfortable stock, stock
 * under its threshold, an outright rupture, a lot expiring within days, a lot
 * already expired, a recalled lot and an article that is tracked but has never
 * been received. Everything goes through the command bus, so the demo data is
 * built by the same invariants as production writes — and leaves the same
 * movement trail behind it.
 */
final class InventoryDemoStory extends Story
{
    private const string PARIS = ClinicDataStory::INDEPENDENT_CLINIC_ID;
    private const string LYON  = ClinicDataStory::GROUP_CLINIC_ID;

    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly Connection $connection,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function build(): void
    {
        foreach ([self::PARIS, self::LYON] as $clinicId) {
            $this->buildFor($clinicId);
        }
    }

    private function buildFor(string $clinicId): void
    {
        $articles = $this->trackedArticles($clinicId);

        if ([] === $articles) {
            return;
        }

        // The first ones get a full shelf, so the list opens on something
        // reassuring rather than on a wall of alerts.
        foreach (\array_slice($articles, 0, 6) as $index => $article) {
            $stockItemId = $this->open($clinicId, $article, threshold: \sprintf('%d.000000', 4 + $index * 2));

            $this->receive($clinicId, $stockItemId, $article, \sprintf('%d.000000', 30 + $index * 12), '+' . (240 + $index * 30) . ' days', 'BL-2026-01' . $index);
            $this->consume($clinicId, $stockItemId, $article, \sprintf('%d.000000', 4 + $index * 5), 'CONSUMPTION_CONSULTATION');
        }

        // Under its threshold: what the clinic should reorder.
        if (isset($articles[6])) {
            $stockItemId = $this->open($clinicId, $articles[6], threshold: '20.000000');
            $this->receive($clinicId, $stockItemId, $articles[6], '24.000000', '+180 days', 'BL-2026-020');
            $this->consume($clinicId, $stockItemId, $articles[6], '18.000000', 'CONSUMPTION_CONSULTATION');
        }

        // Out of stock: received, then entirely consumed.
        if (isset($articles[7])) {
            $stockItemId = $this->open($clinicId, $articles[7], threshold: '10.000000');
            $this->receive($clinicId, $stockItemId, $articles[7], '12.000000', '+150 days', 'BL-2026-021');
            $this->consume($clinicId, $stockItemId, $articles[7], '12.000000', 'SALE_RETAIL');
        }

        // Two lots, the older one expiring within days: this is what FEFO is for.
        if (isset($articles[8])) {
            $stockItemId = $this->open($clinicId, $articles[8], threshold: '8.000000');
            $this->receive($clinicId, $stockItemId, $articles[8], '10.000000', '+9 days', 'BL-2025-114');
            $this->receive($clinicId, $stockItemId, $articles[8], '25.000000', '+320 days', 'BL-2026-022');
        }

        // A lot that has already gone past its date. The receipt refuses a date
        // in the past, so the lot is aged in place and then expired through the
        // domain — which is what takes it out of the usable stock.
        if (isset($articles[9])) {
            $stockItemId = $this->open($clinicId, $articles[9], threshold: '5.000000');
            $this->receive($clinicId, $stockItemId, $articles[9], '15.000000', '+200 days', 'BL-2026-023');
            $this->receiveExpired($clinicId, $stockItemId, $articles[9], '4.000000');

            $this->commandBus->dispatch(new ExpireOutdatedLots($clinicId));
        }

        // A recalled lot: withdrawn from use, with the reason kept.
        if (isset($articles[10])) {
            $stockItemId = $this->open($clinicId, $articles[10], threshold: '5.000000');
            $this->receive($clinicId, $stockItemId, $articles[10], '18.000000', '+260 days', 'BL-2026-024');
            $lotId = $this->receive($clinicId, $stockItemId, $articles[10], '6.000000', '+300 days', 'BL-2026-025');

            if (null !== $lotId) {
                $this->commandBus->dispatch(new MarkLotAsRecalled(
                    stockItemId: $stockItemId,
                    clinicId: $clinicId,
                    lotId: $lotId,
                    reason: 'Rappel laboratoire — défaut de bouchage',
                ));
            }
        }

        // Tracked, watched, but never received: the article someone looks for the
        // day a delivery arrives.
        if (isset($articles[11])) {
            $stockItemId = $this->open($clinicId, $articles[11], threshold: '0.000000');

            $this->commandBus->dispatch(new ChangeStockThreshold(
                stockItemId: $stockItemId,
                clinicId: $clinicId,
                thresholdAmount: '6.000000',
                thresholdUnit: $articles[11]['unit'],
                thresholdType: 'ABSOLUTE',
            ));
        }
    }

    /**
     * @param array{id: string, unit: string} $article
     */
    private function open(string $clinicId, array $article, string $threshold): string
    {
        $stockItemId = $this->commandBus->dispatch(new OpenStockItem(
            clinicId: $clinicId,
            articleId: $article['id'],
            unit: $article['unit'],
            thresholdAmount: $threshold,
            thresholdUnit: $article['unit'],
            thresholdType: 'ABSOLUTE',
            trackStock: true,
        ));

        \assert(\is_string($stockItemId));

        return $stockItemId;
    }

    /**
     * @param array{id: string, unit: string} $article
     *
     * @return string|null the lot that was created
     */
    private function receive(
        string $clinicId,
        string $stockItemId,
        array $article,
        string $quantity,
        string $expiry,
        string $reference,
    ): ?string {
        $this->commandBus->dispatch(new ReceiveStock(
            stockItemId: $stockItemId,
            clinicId: $clinicId,
            lotNumber: $this->lotNumber($reference),
            quantityAmount: $quantity,
            quantityUnit: $article['unit'],
            expiryDate: (new \DateTimeImmutable($expiry))->format('Y-m-d'),
            sourceSupplierId: null,
            occurredAt: null,
            reference: $reference,
            performedBy: null,
        ));

        return $this->lastLotOf($stockItemId);
    }

    /**
     * A lot dated ahead at receipt, then aged in place so the demo has something
     * that really is past its date.
     *
     * @param array{id: string, unit: string} $article
     */
    private function receiveExpired(string $clinicId, string $stockItemId, array $article, string $quantity): void
    {
        $this->receive($clinicId, $stockItemId, $article, $quantity, '+1 day', 'BL-2025-090');

        $lotId = $this->lastLotOf($stockItemId);

        if (null === $lotId) {
            return;
        }

        $this->connection->executeStatement(
            'UPDATE inventory__lots SET expiry_date = :expiry WHERE id = UUID_TO_BIN(:id)',
            [
                'expiry' => (new \DateTimeImmutable('-12 days'))->format('Y-m-d'),
                'id'     => $lotId,
            ],
        );

        // The aggregate was written a moment ago and is still in the identity
        // map with the date it was received on; without this it would be read
        // back from memory and the lot would never look outdated.
        $this->entityManager->clear();
    }

    /**
     * @param array{id: string, unit: string} $article
     */
    private function consume(
        string $clinicId,
        string $stockItemId,
        array $article,
        string $quantity,
        string $reason,
    ): void {
        $this->commandBus->dispatch(new ConsumeStock(
            stockItemId: $stockItemId,
            clinicId: $clinicId,
            quantityAmount: $quantity,
            quantityUnit: $article['unit'],
            occurredAt: null,
            reference: null,
            performedBy: null,
            note: null,
            reason: $reason,
        ));
    }

    private function lotNumber(string $reference): string
    {
        return 'LOT-' . mb_strtoupper(mb_substr(str_replace('-', '', $reference), -6));
    }

    private function lastLotOf(string $stockItemId): ?string
    {
        $lotId = $this->connection->fetchOne(
            'SELECT BIN_TO_UUID(id) FROM inventory__lots
             WHERE stock_item_id = UUID_TO_BIN(:stockItemId)
             ORDER BY created_at DESC, id DESC
             LIMIT 1',
            ['stockItemId' => $stockItemId],
        );

        return \is_string($lotId) ? $lotId : null;
    }

    /**
     * The articles this clinic tracks, oldest code first so the demo is stable
     * from one load to the next.
     *
     * @return list<array{id: string, unit: string}>
     */
    private function trackedArticles(string $clinicId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT BIN_TO_UUID(id) AS id, unit_of_measure
             FROM catalog__articles
             WHERE tenant_id = :clinicId AND track_stock = 1 AND status = 'ACTIVE'
             ORDER BY code ASC",
            ['clinicId' => Uuid::fromString($clinicId)->toBinary()],
        );

        $articles = [];

        foreach ($rows as $row) {
            $articles[] = [
                'id'   => RowAccessor::string($row, 'id'),
                'unit' => RowAccessor::string($row, 'unit_of_measure'),
            ];
        }

        return $articles;
    }
}
