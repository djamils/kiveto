<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\SupplierReceipt\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

final readonly class SupplierReceiptValidated extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'procurement';
    protected const int VERSION            = 1;

    public function __construct(
        private string $receiptId,
        private string $clinicId,
        private string $validatedAt,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->receiptId;
    }

    /** @return array<string, string> */
    public function payload(): array
    {
        return [
            'receiptId'   => $this->receiptId,
            'clinicId'    => $this->clinicId,
            'validatedAt' => $this->validatedAt,
        ];
    }
}
