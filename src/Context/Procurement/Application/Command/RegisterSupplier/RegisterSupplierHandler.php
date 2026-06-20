<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Command\RegisterSupplier;

use App\Context\Procurement\Domain\Supplier\Exception\DuplicateSupplierCodeException;
use App\Context\Procurement\Domain\Supplier\Repository\SupplierRepositoryInterface;
use App\Context\Procurement\Domain\Supplier\Supplier;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierCode;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierIntegrationMode;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierName;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierType;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RegisterSupplierHandler
{
    public function __construct(
        private SupplierRepositoryInterface $supplierRepository,
        private DomainEventPublisher $domainEventPublisher,
        private UuidGeneratorInterface $uuidGenerator,
        private ClockInterface $clock,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(RegisterSupplier $command): void
    {
        $code = SupplierCode::fromString($command->code);

        if (null !== $this->supplierRepository->findByCode($code)) {
            throw new DuplicateSupplierCodeException($command->code);
        }

        $now = $this->clock->now();

        $supplier = Supplier::register(
            id: SupplierId::fromString($this->uuidGenerator->generate()),
            name: SupplierName::fromString($command->name),
            code: $code,
            type: SupplierType::from($command->type),
            countryCode: CountryCode::fromString($command->countryCode),
            defaultCurrency: CurrencyCode::fromString($command->defaultCurrency),
            integrationMode: SupplierIntegrationMode::from($command->integrationMode),
            adapterIdentifier: $command->adapterIdentifier,
            createdAt: $now,
        );

        $this->entityManager->wrapInTransaction(function () use ($supplier): void {
            $this->supplierRepository->save($supplier);
        });

        $this->domainEventPublisher->publish($supplier);
    }
}
