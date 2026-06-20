<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Command\CreateSupplierAccount;

use App\Context\Procurement\Domain\Shared\ValueObject\ClinicId;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\SupplierAccount\Exception\DuplicateSupplierAccountException;
use App\Context\Procurement\Domain\SupplierAccount\Repository\SupplierAccountRepositoryInterface;
use App\Context\Procurement\Domain\SupplierAccount\SupplierAccount;
use App\Context\Procurement\Domain\SupplierAccount\ValueObject\CustomerCode;
use App\Context\Procurement\Domain\SupplierAccount\ValueObject\SupplierAccountId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateSupplierAccountHandler
{
    public function __construct(
        private SupplierAccountRepositoryInterface $accountRepository,
        private DomainEventPublisher $domainEventPublisher,
        private UuidGeneratorInterface $uuidGenerator,
        private ClockInterface $clock,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(CreateSupplierAccount $command): void
    {
        $clinicId   = ClinicId::fromString($command->clinicId);
        $supplierId = SupplierId::fromString($command->supplierId);

        if (null !== $this->accountRepository->findByClinicAndSupplier($clinicId, $supplierId)) {
            throw new DuplicateSupplierAccountException($command->clinicId, $command->supplierId);
        }

        $account = SupplierAccount::create(
            id: SupplierAccountId::fromString($this->uuidGenerator->generate()),
            clinicId: $clinicId,
            supplierId: $supplierId,
            customerCode: CustomerCode::fromString($command->customerCode),
            billingAddress: null,
            defaultDeliveryAddress: null,
            notes: $command->notes,
            createdAt: $this->clock->now(),
        );

        $this->entityManager->wrapInTransaction(function () use ($account): void {
            $this->accountRepository->save($account);
        });

        $this->domainEventPublisher->publish($account);
    }
}
