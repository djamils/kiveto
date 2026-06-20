<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Command\CreateSupplierReceipt;

use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderId;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderLineId;
use App\Context\Procurement\Domain\Shared\ValueObject\ArticleId;
use App\Context\Procurement\Domain\Shared\ValueObject\ClinicId;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\SupplierReceipt\Entity\SupplierReceiptLine;
use App\Context\Procurement\Domain\SupplierReceipt\Repository\SupplierReceiptRepositoryInterface;
use App\Context\Procurement\Domain\SupplierReceipt\SupplierReceipt;
use App\Context\Procurement\Domain\SupplierReceipt\ValueObject\DeliveryNoteReference;
use App\Context\Procurement\Domain\SupplierReceipt\ValueObject\LotInformation;
use App\Context\Procurement\Domain\SupplierReceipt\ValueObject\ReceiptMatchType;
use App\Context\Procurement\Domain\SupplierReceipt\ValueObject\SupplierReceiptId;
use App\Context\Procurement\Domain\SupplierReceipt\ValueObject\SupplierReceiptLineId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateSupplierReceiptHandler
{
    public function __construct(
        private SupplierReceiptRepositoryInterface $receiptRepository,
        private DomainEventPublisher $domainEventPublisher,
        private UuidGeneratorInterface $uuidGenerator,
        private ClockInterface $clock,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(CreateSupplierReceipt $command): void
    {
        $now       = $this->clock->now();
        $receiptId = SupplierReceiptId::fromString($this->uuidGenerator->generate());

        $receipt = SupplierReceipt::create(
            id: $receiptId,
            clinicId: ClinicId::fromString($command->clinicId),
            supplierId: SupplierId::fromString($command->supplierId),
            purchaseOrderId: PurchaseOrderId::fromString($command->purchaseOrderId),
            deliveryNoteReference: DeliveryNoteReference::fromString($command->deliveryNoteReference),
            matchType: ReceiptMatchType::from($command->matchType),
            receivedBy: $command->receivedBy,
            comment: $command->comment,
            createdAt: $now,
        );

        foreach ($command->lines as $lineData) {
            $lotNumber      = $lineData['lotNumber'];
            $lotInformation = null;
            if (null !== $lotNumber) {
                $expiryDateStr  = $lineData['expiryDate'];
                $expiryDate     = null !== $expiryDateStr ? new \DateTimeImmutable($expiryDateStr) : $now;
                $manufacturedAt = null !== $lineData['manufacturedAt']
                    ? new \DateTimeImmutable($lineData['manufacturedAt'])
                    : null;
                $lotInformation = LotInformation::create(
                    $lotNumber,
                    $expiryDate,
                    $manufacturedAt,
                );
            }

            $actualUnitPriceMinor = $lineData['actualUnitPriceMinor'];
            $actualUnitPrice      = null;
            if (null !== $actualUnitPriceMinor) {
                $currency        = $lineData['actualUnitPriceCurrency'] ?? 'EUR';
                $actualUnitPrice = Money::fromMinorUnits($actualUnitPriceMinor, CurrencyCode::fromString($currency));
            }

            $line = SupplierReceiptLine::create(
                id: SupplierReceiptLineId::fromString($this->uuidGenerator->generate()),
                purchaseOrderLineId: PurchaseOrderLineId::fromString($lineData['purchaseOrderLineId']),
                articleId: ArticleId::fromString($lineData['articleId']),
                receivedAmount: $lineData['receivedAmount'],
                receivedUnit: UnitOfMeasure::fromString($lineData['receivedUnit']),
                lotInformation: $lotInformation,
                actualUnitPrice: $actualUnitPrice,
                note: $lineData['note'] ?? null,
            );
            $receipt->addLine($line, $now);
        }

        $this->entityManager->wrapInTransaction(function () use ($receipt): void {
            $this->receiptRepository->save($receipt);
        });
        $this->domainEventPublisher->publish($receipt);
    }
}
