<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\SupplierReceipt\Event;

use App\Shared\Domain\Event\AbstractIntegrationEvent;

readonly class SupplierReceiptCompletedIntegrationEvent extends AbstractIntegrationEvent
{
    protected const string BOUNDED_CONTEXT = 'procurement';
    protected const int VERSION            = 1;

    /**
     * @param array<int, array{
     *     purchaseOrderLineId: string,
     *     articleId: string,
     *     receivedAmount: string,
     *     receivedUnit: string,
     *     lotNumber: ?string,
     *     expiryDate: ?string,
     *     manufacturedAt: ?string,
     *     actualUnitPriceMinor: ?int,
     *     actualUnitPriceCurrency: ?string
     * }> $lines
     */
    public function __construct(
        private string $receiptId,
        private string $clinicId,
        private string $supplierId,
        private string $purchaseOrderId,
        private array $lines,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->receiptId;
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'receiptId'       => $this->receiptId,
            'clinicId'        => $this->clinicId,
            'supplierId'      => $this->supplierId,
            'purchaseOrderId' => $this->purchaseOrderId,
            'lines'           => $this->lines,
        ];
    }

    public function receiptId(): string
    {
        return $this->receiptId;
    }

    public function clinicId(): string
    {
        return $this->clinicId;
    }

    public function supplierId(): string
    {
        return $this->supplierId;
    }

    public function purchaseOrderId(): string
    {
        return $this->purchaseOrderId;
    }

    /**
     * @return array<int, array{
     *     purchaseOrderLineId: string,
     *     articleId: string,
     *     receivedAmount: string,
     *     receivedUnit: string,
     *     lotNumber: ?string,
     *     expiryDate: ?string,
     *     manufacturedAt: ?string,
     *     actualUnitPriceMinor: ?int,
     *     actualUnitPriceCurrency: ?string
     * }>
     */
    public function lines(): array
    {
        return $this->lines;
    }
}
