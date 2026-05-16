<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Infrastructure\ImportSources\France\Dto;

final class AnmvPresentationDto
{
    public function __construct(
        public readonly string $description,
        public readonly ?string $unitCount,
        public readonly ?string $packaging,
        public readonly ?string $gtin,
        public readonly ?string $euPackIdentifier,
        public readonly int $prescriptionCode,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'description'      => $this->description,
            'unitCount'        => $this->unitCount,
            'packaging'        => $this->packaging,
            'gtin'             => $this->gtin,
            'euPackIdentifier' => $this->euPackIdentifier,
            'prescriptionCode' => $this->prescriptionCode,
        ];
    }

    /**
     * @param array{
     *     description: string,
     *     unitCount: string|null,
     *     packaging: string|null,
     *     gtin: string|null,
     *     euPackIdentifier: string|null,
     *     prescriptionCode: int,
     * } $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            description: $data['description'],
            unitCount: $data['unitCount'],
            packaging: $data['packaging'],
            gtin: $data['gtin'],
            euPackIdentifier: $data['euPackIdentifier'],
            prescriptionCode: $data['prescriptionCode'],
        );
    }
}
