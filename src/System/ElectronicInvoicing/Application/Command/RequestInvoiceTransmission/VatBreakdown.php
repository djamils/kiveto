<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission;

/**
 * The VAT of one rate (BG-23): taxable amount (BT-116) and VAT (BT-117).
 */
final readonly class VatBreakdown
{
    public function __construct(
        public string $vatCategory,
        public string $vatRate,
        public int $base,
        public int $vat,
    ) {
    }

    /**
     * @return array{vatCategory: string, vatRate: string, base: int, vat: int}
     */
    public function toArray(): array
    {
        return ['vatCategory' => $this->vatCategory, 'vatRate' => $this->vatRate, 'base' => $this->base, 'vat' => $this->vat];
    }

    /**
     * @param array{vatCategory: string, vatRate: string, base: int, vat: int} $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['vatCategory'], $data['vatRate'], $data['base'], $data['vat']);
    }
}
