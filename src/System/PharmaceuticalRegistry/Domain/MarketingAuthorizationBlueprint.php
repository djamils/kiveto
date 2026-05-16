<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain;

use App\System\PharmaceuticalRegistry\Domain\Entity\Summary;
use App\System\PharmaceuticalRegistry\Domain\Entity\TargetUsage;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\AtcVetCode;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\CommercialName;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ContentHash;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ControlledSubstance;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\HolderLaboratory;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ImportSource;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\JurisdictionalIdentifier;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\MarketingAuthorizationDate;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\MarketingAuthorizationStatus;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\PermanentIdentifier;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\PharmaceuticalForm;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ProductNature;

/** ACL boundary: produced by AnmvCodeMapper (Infrastructure), consumed by MarketingAuthorization::create/updateFromImport (Domain). */
final readonly class MarketingAuthorizationBlueprint
{
    /**
     * @param JurisdictionalIdentifier[] $jurisdictionalIdentifiers
     * @param PresentationBlueprint[]    $presentations
     * @param CompositionBlueprint[]     $compositions
     * @param TargetUsage[]              $targetUsages
     */
    public function __construct(
        public CommercialName $commercialName,
        public HolderLaboratory $holderLaboratory,
        public MarketingAuthorizationStatus $status,
        public MarketingAuthorizationDate $authorizationDate,
        public ProductNature $nature,
        public PharmaceuticalForm $pharmaceuticalForm,
        public ?AtcVetCode $atcVetCode,
        public ?PermanentIdentifier $permanentIdentifier,
        public ?ControlledSubstance $controlledSubstance,
        public array $jurisdictionalIdentifiers,
        public array $presentations,
        public array $compositions,
        public array $targetUsages,
        public ?Summary $summary,
        public ImportSource $source,
        public ContentHash $contentHash,
    ) {
    }
}
