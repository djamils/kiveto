<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Command\SubmitPurchaseOrder;

use App\Context\Procurement\Application\Exception\SupplierAccountDisabledException;
use App\Context\Procurement\Application\Service\SupplierIntegrationDispatcher;
use App\Context\Procurement\Domain\PurchaseOrder\Exception\PurchaseOrderNotFoundException;
use App\Context\Procurement\Domain\PurchaseOrder\Repository\PurchaseOrderRepositoryInterface;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderId;
use App\Context\Procurement\Domain\Supplier\Exception\SupplierNotFoundException;
use App\Context\Procurement\Domain\Supplier\Repository\SupplierRepositoryInterface;
use App\Context\Procurement\Domain\SupplierAccount\Exception\SupplierAccountNotFoundException;
use App\Context\Procurement\Domain\SupplierAccount\Repository\SupplierAccountRepositoryInterface;
use App\Context\Procurement\Domain\SupplierAccount\ValueObject\SupplierAccountStatus;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SubmitPurchaseOrderHandler
{
    public function __construct(
        private PurchaseOrderRepositoryInterface $purchaseOrderRepository,
        private SupplierRepositoryInterface $supplierRepository,
        private SupplierAccountRepositoryInterface $supplierAccountRepository,
        private SupplierIntegrationDispatcher $integrationDispatcher,
        private DomainEventPublisher $domainEventPublisher,
        private ClockInterface $clock,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(SubmitPurchaseOrder $command): void
    {
        $po = $this->purchaseOrderRepository->findById(PurchaseOrderId::fromString($command->purchaseOrderId));
        if (null === $po) {
            throw new PurchaseOrderNotFoundException($command->purchaseOrderId);
        }

        $supplier = $this->supplierRepository->findById($po->supplierId());
        if (null === $supplier) {
            throw new SupplierNotFoundException($po->supplierId()->toString());
        }

        $account = $this->supplierAccountRepository->findById($po->supplierAccountId());
        if (null === $account) {
            throw new SupplierAccountNotFoundException($po->supplierAccountId()->toString());
        }

        // Guard: account must be ACTIVE
        if (SupplierAccountStatus::DISABLED === $account->status()) {
            throw new SupplierAccountDisabledException($account->id()->toString());
        }

        $adapter = $this->integrationDispatcher->dispatch($supplier);
        $now     = $this->clock->now();

        // Phase 1: mark as SUBMITTING inside transaction
        $this->entityManager->wrapInTransaction(function () use ($po, $now): void {
            $po->markAsSubmitting($now);
            $this->purchaseOrderRepository->save($po);
        });
        $this->domainEventPublisher->publish($po);

        // Phase 2: send the order OUTSIDE transaction
        $result = $adapter->sendOrder($po, $account);

        if ($result->success && null !== $result->externalReference) {
            // Phase 3a: SUCCESS — transition to SUBMITTED
            $this->entityManager->wrapInTransaction(function () use ($po, $result, $now): void {
                $po->submit($result->externalReference, $result->sentAt, $now);
                $this->purchaseOrderRepository->save($po);
            });
        } else {
            // Phase 3b: FAILURE — transition to SEND_FAILED; do NOT re-throw
            $this->entityManager->wrapInTransaction(function () use ($po, $result, $now): void {
                $po->markAsSendingFailed($result->errorMessage ?? 'Unknown error', $now);
                $this->purchaseOrderRepository->save($po);
            });
        }

        $this->domainEventPublisher->publish($po);
    }
}
