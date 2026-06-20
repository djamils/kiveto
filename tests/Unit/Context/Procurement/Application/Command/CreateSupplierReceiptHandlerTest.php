<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Application\Command;

use App\Context\Procurement\Application\Command\CreateSupplierReceipt\CreateSupplierReceipt;
use App\Context\Procurement\Application\Command\CreateSupplierReceipt\CreateSupplierReceiptHandler;
use App\Context\Procurement\Domain\SupplierReceipt\Repository\SupplierReceiptRepositoryInterface;
use App\Context\Procurement\Domain\SupplierReceipt\SupplierReceipt;
use App\Context\Procurement\Domain\SupplierReceipt\ValueObject\ReceiptMatchType;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class CreateSupplierReceiptHandlerTest extends TestCase
{
    private const string RECEIPT_UUID  = '01932b00-0000-7000-8000-000000000400';
    private const string LINE_UUID     = '01932b00-0000-7000-8000-000000000401';
    private const string PO_LINE_UUID  = '01932b00-0000-7000-8000-000000000101';
    private const string CLINIC_UUID   = '01932b00-0000-7000-8000-000000000003';
    private const string SUPPLIER_UUID = '01932b00-0000-7000-8000-000000000001';
    private const string PO_UUID       = '01932b00-0000-7000-8000-000000000100';
    private const string ARTICLE_UUID  = '01932b00-0000-7000-8000-000000000200';

    public function testItCreatesReceiptWithLines(): void
    {
        $savedReceipt = null;

        $repository = $this->createMock(SupplierReceiptRepositoryInterface::class);
        $repository
            ->expects(self::once())
            ->method('save')
            ->willReturnCallback(static function (SupplierReceipt $receipt) use (&$savedReceipt): void {
                $savedReceipt = $receipt;
            })
        ;

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())->method('publish');

        ($this->makeHandler($repository, $eventBus))(new CreateSupplierReceipt(
            clinicId: self::CLINIC_UUID,
            supplierId: self::SUPPLIER_UUID,
            purchaseOrderId: self::PO_UUID,
            deliveryNoteReference: 'DN-001',
            matchType: ReceiptMatchType::AUTO_MATCHED->value,
            receivedBy: null,
            comment: null,
            lines: [
                [
                    'purchaseOrderLineId'     => self::PO_LINE_UUID,
                    'articleId'               => self::ARTICLE_UUID,
                    'receivedAmount'          => '5',
                    'receivedUnit'            => 'UNIT',
                    'lotNumber'               => null,
                    'expiryDate'              => null,
                    'manufacturedAt'          => null,
                    'actualUnitPriceMinor'    => null,
                    'actualUnitPriceCurrency' => null,
                    'note'                    => null,
                ],
            ],
        ));

        self::assertNotNull($savedReceipt);
        self::assertCount(1, $savedReceipt->lines());
    }

    public function testItCreatesReceiptWithLotInformation(): void
    {
        $savedReceipt = null;

        $repository = $this->createMock(SupplierReceiptRepositoryInterface::class);
        $repository
            ->expects(self::once())
            ->method('save')
            ->willReturnCallback(static function (SupplierReceipt $receipt) use (&$savedReceipt): void {
                $savedReceipt = $receipt;
            })
        ;

        $eventBus = $this->createStub(EventBusInterface::class);

        ($this->makeHandler($repository, $eventBus))(new CreateSupplierReceipt(
            clinicId: self::CLINIC_UUID,
            supplierId: self::SUPPLIER_UUID,
            purchaseOrderId: self::PO_UUID,
            deliveryNoteReference: 'DN-002',
            matchType: ReceiptMatchType::AUTO_MATCHED->value,
            receivedBy: null,
            comment: 'Test receipt',
            lines: [
                [
                    'purchaseOrderLineId'     => self::PO_LINE_UUID,
                    'articleId'               => self::ARTICLE_UUID,
                    'receivedAmount'          => '10',
                    'receivedUnit'            => 'UNIT',
                    'lotNumber'               => 'LOT-ABC',
                    'expiryDate'              => '2027-01-01',
                    'manufacturedAt'          => null,
                    'actualUnitPriceMinor'    => 500,
                    'actualUnitPriceCurrency' => 'EUR',
                    'note'                    => null,
                ],
            ],
        ));

        self::assertNotNull($savedReceipt);
        $line = $savedReceipt->lines()[0];
        self::assertNotNull($line->lotInformation());
        self::assertSame('LOT-ABC', $line->lotInformation()->lotNumber);
    }

    public function testItCreatesReceiptWithLotInformationIncludingManufacturedAt(): void
    {
        $savedReceipt = null;

        $repository = $this->createMock(SupplierReceiptRepositoryInterface::class);
        $repository
            ->expects(self::once())
            ->method('save')
            ->willReturnCallback(static function (SupplierReceipt $receipt) use (&$savedReceipt): void {
                $savedReceipt = $receipt;
            })
        ;

        $eventBus = $this->createStub(EventBusInterface::class);

        ($this->makeHandler($repository, $eventBus))(new CreateSupplierReceipt(
            clinicId: self::CLINIC_UUID,
            supplierId: self::SUPPLIER_UUID,
            purchaseOrderId: self::PO_UUID,
            deliveryNoteReference: 'DN-003',
            matchType: ReceiptMatchType::AUTO_MATCHED->value,
            receivedBy: null,
            comment: null,
            lines: [
                [
                    'purchaseOrderLineId'     => self::PO_LINE_UUID,
                    'articleId'               => self::ARTICLE_UUID,
                    'receivedAmount'          => '5',
                    'receivedUnit'            => 'UNIT',
                    'lotNumber'               => 'LOT-XYZ',
                    'expiryDate'              => '2027-12-31',
                    'manufacturedAt'          => '2026-01-10',
                    'actualUnitPriceMinor'    => null,
                    'actualUnitPriceCurrency' => null,
                    'note'                    => null,
                ],
            ],
        ));

        self::assertNotNull($savedReceipt);
        $line = $savedReceipt->lines()[0];
        self::assertNotNull($line->lotInformation());
        self::assertNotNull($line->lotInformation()->manufacturedAt);
        self::assertSame('2026-01-10', $line->lotInformation()->manufacturedAt->format('Y-m-d'));
    }

    private function makeHandler(
        SupplierReceiptRepositoryInterface $repository,
        EventBusInterface $eventBus,
    ): CreateSupplierReceiptHandler {
        $uuidGenerator = $this->createStub(UuidGeneratorInterface::class);
        $uuidGenerator->method('generate')->willReturnOnConsecutiveCalls(
            self::RECEIPT_UUID,
            self::LINE_UUID,
            self::LINE_UUID,
            self::LINE_UUID,
        );

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-01-15'));

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('wrapInTransaction')->willReturnCallback(static function (callable $fn): void {
            $fn();
        });

        return new CreateSupplierReceiptHandler(
            $repository,
            new DomainEventPublisher($eventBus),
            $uuidGenerator,
            $clock,
            $em,
        );
    }
}
