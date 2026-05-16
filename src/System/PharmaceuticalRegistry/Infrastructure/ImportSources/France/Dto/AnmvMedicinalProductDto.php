<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Infrastructure\ImportSources\France\Dto;

use App\System\PharmaceuticalRegistry\Domain\ValueObject\ContentHash;

/**
 * All numeric fields MUST be cast to string before storage to ensure ContentHash determinism across environments.
 * PHP's json_encode of float values is platform/locale-dependent in edge cases.
 */
final class AnmvMedicinalProductDto
{
    public readonly ContentHash $contentHash;

    /**
     * @param AnmvPresentationDto[]       $presentations
     * @param AnmvCompositionDto[]        $compositions
     * @param AnmvVoieAdministrationDto[] $voiesAdministration
     */
    public function __construct(
        public readonly string $authorityIdentifier,
        public readonly string $commercialName,
        public readonly string $holderLaboratoryLabel,
        public readonly int $statusCode,
        public readonly string $authorizationDate,
        public readonly int $natureCode,
        public readonly string $pharmaceuticalFormLabel,
        public readonly ?string $atcVetCode,
        public readonly ?string $permanentIdentifier,
        public readonly array $presentations,
        public readonly array $compositions,
        public readonly array $voiesAdministration,
    ) {
        $this->contentHash = ContentHash::of($this->toArray());
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'authorityIdentifier'     => $this->authorityIdentifier,
            'commercialName'          => $this->commercialName,
            'holderLaboratoryLabel'   => $this->holderLaboratoryLabel,
            'statusCode'              => (string) $this->statusCode,
            'authorizationDate'       => $this->authorizationDate,
            'natureCode'              => (string) $this->natureCode,
            'pharmaceuticalFormLabel' => $this->pharmaceuticalFormLabel,
            'atcVetCode'              => $this->atcVetCode,
            'permanentIdentifier'     => $this->permanentIdentifier,
            'presentations'           => array_map(
                static fn (AnmvPresentationDto $p) => $p->toArray(),
                $this->presentations,
            ),
            'compositions' => array_map(
                static fn (AnmvCompositionDto $c) => $c->toArray(),
                $this->compositions,
            ),
            'voiesAdministration' => array_map(
                static fn (AnmvVoieAdministrationDto $v) => $v->toArray(),
                $this->voiesAdministration,
            ),
        ];
    }

    /**
     * @param array{
     *     authorityIdentifier: string,
     *     commercialName: string,
     *     holderLaboratoryLabel: string,
     *     statusCode: string,
     *     authorizationDate: string,
     *     natureCode: string,
     *     pharmaceuticalFormLabel: string,
     *     atcVetCode: string|null,
     *     permanentIdentifier: string|null,
     *     presentations: array<int, array{
     *         description: string, unitCount: string|null, packaging: string|null,
     *         gtin: string|null, euPackIdentifier: string|null, prescriptionCode: int
     *     }>,
     *     compositions: array<int, array{
     *         activeSubstanceLabel: string, quantityValue: string|null,
     *         quantityUnitLabel: string|null, quantityUnitCode: string|null, isExcipient: bool
     *     }>,
     *     voiesAdministration: array<int, array{
     *         routeCode: int, speciesCode: int, foodProductionCode: int|null,
     *         withdrawalPeriodQuantity: string|null, withdrawalPeriodUnitCode: int|null,
     *         jurisdictionalNote: string|null
     *     }>,
     * } $data
     */
    public static function fromArray(array $data): self
    {
        $presentations = array_map(
            static fn (array $p) => AnmvPresentationDto::fromArray($p),
            $data['presentations'],
        );

        $compositions = array_map(
            static fn (array $c) => AnmvCompositionDto::fromArray($c),
            $data['compositions'],
        );

        $voies = array_map(
            static fn (array $v) => AnmvVoieAdministrationDto::fromArray($v),
            $data['voiesAdministration'],
        );

        return new self(
            authorityIdentifier: $data['authorityIdentifier'],
            commercialName: $data['commercialName'],
            holderLaboratoryLabel: $data['holderLaboratoryLabel'],
            statusCode: (int) $data['statusCode'],
            authorizationDate: $data['authorizationDate'],
            natureCode: (int) $data['natureCode'],
            pharmaceuticalFormLabel: $data['pharmaceuticalFormLabel'],
            atcVetCode: $data['atcVetCode'],
            permanentIdentifier: $data['permanentIdentifier'],
            presentations: $presentations,
            compositions: $compositions,
            voiesAdministration: $voies,
        );
    }
}
