<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\SupplierAccount\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

final readonly class SupplierAccountDisabled extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'procurement';
    protected const int VERSION            = 1;

    public function __construct(
        private string $accountId,
        private string $clinicId,
        private string $supplierId,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->accountId;
    }

    /** @return array<string, string> */
    public function payload(): array
    {
        return [
            'accountId'  => $this->accountId,
            'clinicId'   => $this->clinicId,
            'supplierId' => $this->supplierId,
        ];
    }
}
