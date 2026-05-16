<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Infrastructure\ImportSources\France\Dto;

final class AnmvVoieAdministrationDto
{
    public function __construct(
        public readonly int $routeCode,
        public readonly int $speciesCode,
        public readonly ?int $foodProductionCode,
        public readonly ?string $withdrawalPeriodQuantity,
        public readonly ?int $withdrawalPeriodUnitCode,
        public readonly ?string $jurisdictionalNote,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'routeCode'                => $this->routeCode,
            'speciesCode'              => $this->speciesCode,
            'foodProductionCode'       => $this->foodProductionCode,
            'withdrawalPeriodQuantity' => $this->withdrawalPeriodQuantity,
            'withdrawalPeriodUnitCode' => $this->withdrawalPeriodUnitCode,
            'jurisdictionalNote'       => $this->jurisdictionalNote,
        ];
    }

    /**
     * @param array{
     *     routeCode: int,
     *     speciesCode: int,
     *     foodProductionCode: int|null,
     *     withdrawalPeriodQuantity: string|null,
     *     withdrawalPeriodUnitCode: int|null,
     *     jurisdictionalNote: string|null,
     * } $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            routeCode: $data['routeCode'],
            speciesCode: $data['speciesCode'],
            foodProductionCode: $data['foodProductionCode'],
            withdrawalPeriodQuantity: $data['withdrawalPeriodQuantity'],
            withdrawalPeriodUnitCode: $data['withdrawalPeriodUnitCode'],
            jurisdictionalNote: $data['jurisdictionalNote'],
        );
    }
}
