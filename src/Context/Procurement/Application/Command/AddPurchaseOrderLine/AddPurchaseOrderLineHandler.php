<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Command\AddPurchaseOrderLine;

use App\Context\Procurement\Application\Exception\DiscontinuedCatalogEntryException;
use App\Context\Procurement\Application\Port\SupplierCatalogReadRepositoryInterface;
use App\Context\Procurement\Domain\PurchaseOrder\Exception\PurchaseOrderNotFoundException;
use App\Context\Procurement\Domain\PurchaseOrder\Repository\PurchaseOrderRepositoryInterface;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderId;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderLineId;
use App\Context\Procurement\Domain\Shared\ValueObject\ArticleId;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierCatalogEntryId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AddPurchaseOrderLineHandler
{
    public function __construct(
        private PurchaseOrderRepositoryInterface $purchaseOrderRepository,
        private SupplierCatalogReadRepositoryInterface $catalogReadRepository,
        private DomainEventPublisher $domainEventPublisher,
        private UuidGeneratorInterface $uuidGenerator,
        private ClockInterface $clock,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(AddPurchaseOrderLine $command): void
    {
        $po = $this->purchaseOrderRepository->findById(PurchaseOrderId::fromString($command->purchaseOrderId));
        if (null === $po) {
            throw new PurchaseOrderNotFoundException($command->purchaseOrderId);
        }

        // Guard: reject DISCONTINUED catalog entries at the Application layer
        $catalogEntry = $this->catalogReadRepository->findById($command->catalogEntryId);
        if (null !== $catalogEntry && 'DISCONTINUED' === ($catalogEntry['status'] ?? '')) {
            throw new DiscontinuedCatalogEntryException($command->catalogEntryId);
        }

        $now    = $this->clock->now();
        $lineId = PurchaseOrderLineId::fromString($this->uuidGenerator->generate());

        if (!is_numeric($command->orderedAmount)) {
            throw new \InvalidArgumentException(\sprintf('orderedAmount "%s" is not numeric.', $command->orderedAmount));
        }

        /** @var numeric-string $orderedAmount */
        $orderedAmount = $command->orderedAmount;

        $po->addLine(
            lineId: $lineId,
            articleId: ArticleId::fromString($command->articleId),
            catalogEntryId: SupplierCatalogEntryId::fromString($command->catalogEntryId),
            orderedAmount: $orderedAmount,
            orderedUnit: UnitOfMeasure::fromString($command->orderedUnit),
            unitPrice: Money::fromMinorUnits($command->unitPriceMinor, CurrencyCode::fromString($command->unitPriceCurrency)),
            note: $command->note,
            updatedAt: $now,
        );

        $this->entityManager->wrapInTransaction(function () use ($po): void {
            $this->purchaseOrderRepository->save($po);
        });
        $this->domainEventPublisher->publish($po);
    }
}
