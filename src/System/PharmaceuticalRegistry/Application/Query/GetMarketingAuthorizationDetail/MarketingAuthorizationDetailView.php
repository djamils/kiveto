<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Query\GetMarketingAuthorizationDetail;

readonly class MarketingAuthorizationDetailView
{
    /**
     * @param array<mixed> $presentations
     * @param array<mixed> $activeSubstances
     * @param array<mixed> $targetUsages
     * @param array<mixed> $jurisdictionalIdentifiers
     */
    public function __construct(
        public string $id,
        public string $commercialName,
        public string $holderLaboratory,
        public string $status,
        public string $authorizationDate,
        public string $nature,
        public string $pharmaceuticalForm,
        public ?string $atcVetCode,
        public ?string $permanentIdentifier,
        public ?string $controlledSubstanceClass,
        public array $presentations,
        public array $activeSubstances,
        public array $targetUsages,
        public array $jurisdictionalIdentifiers,
        public string $lastImportSource,
        public \DateTimeImmutable $lastImportedAt,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
    }
}
