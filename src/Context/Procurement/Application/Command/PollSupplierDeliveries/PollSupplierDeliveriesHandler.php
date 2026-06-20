<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Command\PollSupplierDeliveries;

use App\Context\Procurement\Application\Service\IncomingDeliveryProcessor;
use App\Context\Procurement\Application\Service\SupplierIntegrationDispatcher;
use App\Context\Procurement\Domain\Supplier\Exception\SupplierNotFoundException;
use App\Context\Procurement\Domain\Supplier\Repository\SupplierRepositoryInterface;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\SupplierAccount\Exception\SupplierAccountNotFoundException;
use App\Context\Procurement\Domain\SupplierAccount\Repository\SupplierAccountRepositoryInterface;
use App\Context\Procurement\Domain\SupplierAccount\ValueObject\SupplierAccountId;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class PollSupplierDeliveriesHandler
{
    public function __construct(
        private SupplierRepositoryInterface $supplierRepository,
        private SupplierAccountRepositoryInterface $supplierAccountRepository,
        private SupplierIntegrationDispatcher $integrationDispatcher,
        private IncomingDeliveryProcessor $incomingDeliveryProcessor,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(PollSupplierDeliveries $command): void
    {
        $supplier = $this->supplierRepository->findById(SupplierId::fromString($command->supplierId));
        if (null === $supplier) {
            throw new SupplierNotFoundException($command->supplierId);
        }

        $account = $this->supplierAccountRepository->findById(SupplierAccountId::fromString($command->supplierAccountId));
        if (null === $account) {
            throw new SupplierAccountNotFoundException($command->supplierAccountId);
        }

        $adapter = $this->integrationDispatcher->dispatch($supplier);

        // Skip adapters that do not support asynchronous delivery polling
        if (!$adapter->supportsAsyncDeliveryPolling()) {
            return;
        }

        $until      = $this->clock->now();
        $since      = $until->modify('-24 hours');
        $deliveries = $adapter->fetchDeliveries($supplier, $account, $since, $until);

        $this->incomingDeliveryProcessor->process($supplier, $account, $deliveries);
    }
}
