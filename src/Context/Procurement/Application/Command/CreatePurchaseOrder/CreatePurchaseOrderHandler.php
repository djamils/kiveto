<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Command\CreatePurchaseOrder;

use App\Context\Procurement\Application\Port\ClinicProviderInterface;
use App\Context\Procurement\Domain\PurchaseOrder\PurchaseOrder;
use App\Context\Procurement\Domain\PurchaseOrder\Repository\PurchaseOrderRepositoryInterface;
use App\Context\Procurement\Domain\PurchaseOrder\Service\PurchaseOrderNumberGeneratorInterface;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderId;
use App\Context\Procurement\Domain\Shared\ValueObject\Address;
use App\Context\Procurement\Domain\Shared\ValueObject\ClinicId;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\SupplierAccount\ValueObject\SupplierAccountId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreatePurchaseOrderHandler
{
    public function __construct(
        private PurchaseOrderRepositoryInterface $purchaseOrderRepository,
        private PurchaseOrderNumberGeneratorInterface $numberGenerator,
        private ClinicProviderInterface $clinicProvider,
        private DomainEventPublisher $domainEventPublisher,
        private UuidGeneratorInterface $uuidGenerator,
        private ClockInterface $clock,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(CreatePurchaseOrder $command): void
    {
        $clinicId          = ClinicId::fromString($command->clinicId);
        $supplierId        = SupplierId::fromString($command->supplierId);
        $supplierAccountId = SupplierAccountId::fromString($command->supplierAccountId);

        $now      = $this->clock->now();
        $year     = (int) $now->format('Y');
        $currency = CurrencyCode::fromString($this->clinicProvider->getCurrency($clinicId));
        $poId     = PurchaseOrderId::fromString($this->uuidGenerator->generate());

        $countryCodeValue = $command->deliveryAddress['countryCode'] ?? null;
        $deliveryAddress  = Address::create(
            $command->deliveryAddress['street'] ?? null,
            $command->deliveryAddress['addressLine2'] ?? null,
            $command->deliveryAddress['postalCode'] ?? null,
            $command->deliveryAddress['city'] ?? null,
            null !== $countryCodeValue ? CountryCode::fromString($countryCodeValue) : null,
        );

        // Number generator uses SELECT FOR UPDATE — must be inside transaction
        $po = null;
        $this->entityManager->wrapInTransaction(function () use ($poId, $clinicId, $supplierId, $supplierAccountId, $currency, $deliveryAddress, $now, $year, &$po): void {
            $number = $this->numberGenerator->next($clinicId, $year);
            $po     = PurchaseOrder::create(
                id: $poId,
                clinicId: $clinicId,
                supplierId: $supplierId,
                supplierAccountId: $supplierAccountId,
                orderNumber: $number,
                currency: $currency,
                deliveryAddress: $deliveryAddress,
                createdAt: $now,
            );
            $this->purchaseOrderRepository->save($po);
        });

        \assert($po instanceof PurchaseOrder);
        $this->domainEventPublisher->publish($po);
    }
}
