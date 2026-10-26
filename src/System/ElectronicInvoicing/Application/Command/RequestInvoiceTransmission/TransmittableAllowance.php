<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission;

/**
 * A document-level allowance (BG-20) at one VAT rate.
 */
final readonly class TransmittableAllowance
{
    public function __construct(
        public int $amount,
        public string $vatCategory,
        public string $vatRate,
        public string $reason,
    ) {
    }

    /**
     * @return array{amount: int, vatCategory: string, vatRate: string, reason: string}
     */
    public function toArray(): array
    {
        return ['amount' => $this->amount, 'vatCategory' => $this->vatCategory, 'vatRate' => $this->vatRate, 'reason' => $this->reason];
    }

    /**
     * @param array{amount: int, vatCategory: string, vatRate: string, reason: string} $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['amount'], $data['vatCategory'], $data['vatRate'], $data['reason']);
    }
}
