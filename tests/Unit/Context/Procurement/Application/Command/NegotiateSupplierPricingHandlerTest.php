<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Application\Command;

use App\Context\Procurement\Application\Command\NegotiateSupplierPricing\NegotiateSupplierPricing;
use App\Context\Procurement\Application\Command\NegotiateSupplierPricing\NegotiateSupplierPricingHandler;
use App\Context\Procurement\Domain\Shared\ValueObject\ClinicId;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierCatalogEntryId;
use App\Context\Procurement\Domain\SupplierPricing\Repository\SupplierPricingRepositoryInterface;
use App\Context\Procurement\Domain\SupplierPricing\SupplierPricing;
use App\Context\Procurement\Domain\SupplierPricing\ValueObject\NegotiatedPrice;
use App\Context\Procurement\Domain\SupplierPricing\ValueObject\SupplierPricingId;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class NegotiateSupplierPricingHandlerTest extends TestCase
{
    private const string PRICING_UUID  = '01932b00-0000-7000-8000-000000000004';
    private const string CLINIC_UUID   = '01932b00-0000-7000-8000-000000000010';
    private const string SUPPLIER_UUID = '01932b00-0000-7000-8000-000000000001';
    private const string ENTRY_UUID    = '01932b00-0000-7000-8000-000000000003';

    public function testItNegotiatesPricing(): void
    {
        $repository = $this->createMock(SupplierPricingRepositoryInterface::class);
        $repository->method('findByClinicAndEntry')->willReturn(null);
        $repository->expects(self::once())->method('save');

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())->method('publish');

        $this->makeHandler($repository, $eventBus)(new NegotiateSupplierPricing(
            clinicId: self::CLINIC_UUID,
            supplierId: self::SUPPLIER_UUID,
            catalogEntryId: self::ENTRY_UUID,
            amountMinor: 1200,
            currency: 'EUR',
            discountPercentage: '5',
            notes: null,
            expiresAt: null,
        ));
    }

    public function testItThrowsOnDuplicatePricing(): void
    {
        $existing = SupplierPricing::negotiate(
            id: SupplierPricingId::fromString(self::PRICING_UUID),
            clinicId: ClinicId::fromString(self::CLINIC_UUID),
            supplierId: SupplierId::fromString(self::SUPPLIER_UUID),
            catalogEntryId: SupplierCatalogEntryId::fromString(self::ENTRY_UUID),
            negotiatedPrice: NegotiatedPrice::create(
                Money::fromMinorUnits(1200, CurrencyCode::fromString('EUR')),
            ),
            expiresAt: null,
            negotiatedAt: new \DateTimeImmutable('2024-01-01'),
        );

        $repository = $this->createStub(SupplierPricingRepositoryInterface::class);
        $repository->method('findByClinicAndEntry')->willReturn($existing);

        $this->expectException(\InvalidArgumentException::class);

        $this->makeHandler($repository, $this->createStub(EventBusInterface::class))(new NegotiateSupplierPricing(
            clinicId: self::CLINIC_UUID,
            supplierId: self::SUPPLIER_UUID,
            catalogEntryId: self::ENTRY_UUID,
            amountMinor: 1200,
            currency: 'EUR',
            discountPercentage: null,
            notes: null,
            expiresAt: null,
        ));
    }

    private function makeHandler(
        SupplierPricingRepositoryInterface $repository,
        EventBusInterface $eventBus,
    ): NegotiateSupplierPricingHandler {
        $uuidGenerator = $this->createStub(UuidGeneratorInterface::class);
        $uuidGenerator->method('generate')->willReturn(self::PRICING_UUID);

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2024-01-01'));

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('wrapInTransaction')->willReturnCallback(
            static function (callable $fn): void {
                $fn();
            },
        );

        return new NegotiateSupplierPricingHandler(
            $repository,
            new DomainEventPublisher($eventBus),
            $uuidGenerator,
            $clock,
            $em,
        );
    }
}
