<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Command\UpdateSupplierAccount;

use App\Context\Procurement\Domain\Shared\ValueObject\Address;
use App\Context\Procurement\Domain\SupplierAccount\Exception\SupplierAccountNotFoundException;
use App\Context\Procurement\Domain\SupplierAccount\Repository\SupplierAccountRepositoryInterface;
use App\Context\Procurement\Domain\SupplierAccount\ValueObject\CustomerCode;
use App\Context\Procurement\Domain\SupplierAccount\ValueObject\SupplierAccountId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateSupplierAccountHandler
{
    public function __construct(
        private SupplierAccountRepositoryInterface $accountRepository,
        private DomainEventPublisher $domainEventPublisher,
        private ClockInterface $clock,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(UpdateSupplierAccount $command): void
    {
        $account = $this->accountRepository->findById(SupplierAccountId::fromString($command->accountId));

        if (null === $account) {
            throw new SupplierAccountNotFoundException($command->accountId);
        }

        $account->update(
            customerCode: CustomerCode::fromString($command->customerCode),
            billingAddress: $this->buildAddress($command->billingAddress),
            defaultDeliveryAddress: $this->buildAddress($command->deliveryAddress),
            notes: $command->notes,
            updatedAt: $this->clock->now(),
        );

        $this->entityManager->wrapInTransaction(function () use ($account): void {
            $this->accountRepository->save($account);
        });

        $this->domainEventPublisher->publish($account);
    }

    /** @param array<string, string|null>|null $data */
    private function buildAddress(?array $data): ?Address
    {
        if (null === $data) {
            return null;
        }

        return Address::fromArray($data);
    }
}
