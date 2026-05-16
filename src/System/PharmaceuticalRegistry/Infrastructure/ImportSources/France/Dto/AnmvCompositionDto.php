<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Infrastructure\ImportSources\France\Dto;

final class AnmvCompositionDto
{
    public function __construct(
        public readonly string $activeSubstanceLabel,
        public readonly ?string $quantityValue,
        public readonly ?string $quantityUnitLabel,
        public readonly ?string $quantityUnitCode,
        public readonly bool $isExcipient,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'activeSubstanceLabel' => $this->activeSubstanceLabel,
            'quantityValue'        => $this->quantityValue,
            'quantityUnitLabel'    => $this->quantityUnitLabel,
            'quantityUnitCode'     => $this->quantityUnitCode,
            'isExcipient'          => $this->isExcipient,
        ];
    }

    /**
     * @param array{
     *     activeSubstanceLabel: string,
     *     quantityValue: string|null,
     *     quantityUnitLabel: string|null,
     *     quantityUnitCode: string|null,
     *     isExcipient: bool,
     * } $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            activeSubstanceLabel: $data['activeSubstanceLabel'],
            quantityValue: $data['quantityValue'],
            quantityUnitLabel: $data['quantityUnitLabel'],
            quantityUnitCode: $data['quantityUnitCode'],
            isExcipient: $data['isExcipient'],
        );
    }
}
