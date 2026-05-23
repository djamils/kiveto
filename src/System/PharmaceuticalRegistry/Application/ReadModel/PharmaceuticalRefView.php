<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\ReadModel;

readonly class PharmaceuticalRefView
{
    /**
     * @param array<mixed> $presentations
     * @param string[]     $activeSubstanceLabels
     */
    public function __construct(
        public string $id,
        public string $commercialName,
        public string $holderLaboratory,
        public string $status,
        public ?string $atcVetCode,
        public ?string $permanentIdentifier,
        public array $presentations,
        public array $activeSubstanceLabels,
        public string $pharmaceuticalForm,
        public string $lastImportSource,
        public \DateTimeImmutable $lastImportedAt,
        public ?string $controlledSubstanceClass = null,
        public ?string $gtin = null,
    ) {
    }
}
