<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission;

/**
 * One invoice line (BG-25). Amounts in minor units; the unit net price is a
 * decimal string with six places (BT-146); the VAT rate a decimal string.
 */
final readonly class TransmittableLine
{
    public function __construct(
        public string $lineId,
        public string $name,
        public string $quantity,
        public string $unitCode,
        public string $unitNet,
        public int $net,
        public string $vatCategory,
        public string $vatRate,
    ) {
    }

    /**
     * @return array{lineId: string, name: string, quantity: string, unitCode: string, unitNet: string, net: int, vatCategory: string, vatRate: string}
     */
    public function toArray(): array
    {
        return [
            'lineId'      => $this->lineId,
            'name'        => $this->name,
            'quantity'    => $this->quantity,
            'unitCode'    => $this->unitCode,
            'unitNet'     => $this->unitNet,
            'net'         => $this->net,
            'vatCategory' => $this->vatCategory,
            'vatRate'     => $this->vatRate,
        ];
    }

    /**
     * @param array{lineId: string, name: string, quantity: string, unitCode: string, unitNet: string, net: int, vatCategory: string, vatRate: string} $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['lineId'], $data['name'], $data['quantity'], $data['unitCode'], $data['unitNet'], $data['net'], $data['vatCategory'], $data['vatRate']);
    }
}
