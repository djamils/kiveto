<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Application\Command;

use App\Context\Procurement\Application\Command\CreatePurchaseOrder\CreatePurchaseOrder;
use App\Context\Procurement\Application\Command\CreatePurchaseOrder\CreatePurchaseOrderHandler;
use App\Context\Procurement\Application\Port\ClinicProviderInterface;
use App\Context\Procurement\Domain\PurchaseOrder\Repository\PurchaseOrderRepositoryInterface;
use App\Context\Procurement\Domain\PurchaseOrder\Service\PurchaseOrderNumberGeneratorInterface;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderNumber;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class CreatePurchaseOrderHandlerTest extends TestCase
{
    private const string PO_UUID       = '01932b00-0000-7000-8000-000000000100';
    private const string CLINIC_UUID   = '01932b00-0000-7000-8000-000000000003';
    private const string SUPPLIER_UUID = '01932b00-0000-7000-8000-000000000001';
    private const string ACCOUNT_UUID  = '01932b00-0000-7000-8000-000000000002';

    public function testItCreatesPurchaseOrderAndPublishesEvent(): void
    {
        $numberGenerator = $this->createStub(PurchaseOrderNumberGeneratorInterface::class);
        $numberGenerator->method('next')->willReturn(PurchaseOrderNumber::fromString('PO-2026-000001'));

        $clinicProvider = $this->createStub(ClinicProviderInterface::class);
        $clinicProvider->method('getCurrency')->willReturn('EUR');

        $uuidGenerator = $this->createStub(UuidGeneratorInterface::class);
        $uuidGenerator->method('generate')->willReturn(self::PO_UUID);

        $repository = $this->createMock(PurchaseOrderRepositoryInterface::class);
        $repository->expects(self::once())->method('save');

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())->method('publish');

        $handler = $this->makeHandler($repository, $numberGenerator, $clinicProvider, $eventBus, $uuidGenerator);

        ($handler)(new CreatePurchaseOrder(
            clinicId: self::CLINIC_UUID,
            supplierId: self::SUPPLIER_UUID,
            supplierAccountId: self::ACCOUNT_UUID,
            deliveryAddress: ['street' => '1 Rue de la Paix', 'city' => 'Paris', 'postalCode' => '75001', 'countryCode' => 'FR'],
        ));
    }

    public function testItHandlesEmptyDeliveryAddress(): void
    {
        $numberGenerator = $this->createStub(PurchaseOrderNumberGeneratorInterface::class);
        $numberGenerator->method('next')->willReturn(PurchaseOrderNumber::fromString('PO-2026-000001'));

        $clinicProvider = $this->createStub(ClinicProviderInterface::class);
        $clinicProvider->method('getCurrency')->willReturn('EUR');

        $uuidGenerator = $this->createStub(UuidGeneratorInterface::class);
        $uuidGenerator->method('generate')->willReturn(self::PO_UUID);

        $repository = $this->createStub(PurchaseOrderRepositoryInterface::class);
        $eventBus   = $this->createStub(EventBusInterface::class);

        $handler = $this->makeHandler($repository, $numberGenerator, $clinicProvider, $eventBus, $uuidGenerator);

        ($handler)(new CreatePurchaseOrder(
            clinicId: self::CLINIC_UUID,
            supplierId: self::SUPPLIER_UUID,
            supplierAccountId: self::ACCOUNT_UUID,
            deliveryAddress: [],
        ));

        $this->addToAssertionCount(1);
    }

    private function makeHandler(
        PurchaseOrderRepositoryInterface $repository,
        PurchaseOrderNumberGeneratorInterface $numberGenerator,
        ClinicProviderInterface $clinicProvider,
        EventBusInterface $eventBus,
        UuidGeneratorInterface $uuidGenerator,
    ): CreatePurchaseOrderHandler {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-01-15'));

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('wrapInTransaction')->willReturnCallback(static function (callable $fn): void {
            $fn();
        });

        return new CreatePurchaseOrderHandler(
            $repository,
            $numberGenerator,
            $clinicProvider,
            new DomainEventPublisher($eventBus),
            $uuidGenerator,
            $clock,
            $em,
        );
    }
}
