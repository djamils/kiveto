<?php

declare(strict_types=1);

namespace App\Context\Catalog\Infrastructure\Persistence\Doctrine\Mapper;

use App\Context\Catalog\Domain\Article\Article;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleCode;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleId;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleKind;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleName;
use App\Context\Catalog\Domain\Article\ValueObject\DrugProperties;
use App\Context\Catalog\Domain\Article\ValueObject\Gtin;
use App\Context\Catalog\Domain\Article\ValueObject\MarketingAuthorizationRef;
use App\Context\Catalog\Domain\Article\ValueObject\PrescriptionClass;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Context\Catalog\Infrastructure\Persistence\Doctrine\Entity\ArticleEntity;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use Symfony\Component\Uid\Uuid;

final readonly class ArticleMapper
{
    public function toEntity(Article $article, ?ArticleEntity $existing = null): ArticleEntity
    {
        $entity = $existing ?? new ArticleEntity();

        $entity->setId(Uuid::fromString($article->id()->toString()));
        $entity->setTenantId(Uuid::fromString($article->clinicId()->toString()));
        $entity->setName($article->name()->toString());
        $entity->setCode($article->code()->toString());
        $entity->setKind($article->kind());
        $entity->setGtin($article->gtin()?->toString());
        $entity->setTaxCategoryCode($article->taxCategory()->toString());
        $entity->setBasePriceMinorUnits($article->basePrice()->minorUnits());
        $entity->setBasePriceCurrency($article->basePrice()->currency()->toString());
        $entity->setUnitOfMeasure($article->unitOfMeasure()->toString());
        $entity->setTrackStock($article->trackStock());
        $entity->setStatus($article->status());
        $entity->setCreatedAt($article->createdAt());
        $entity->setUpdatedAt($article->updatedAt());

        $drugProps = $article->drugProperties();

        if (null !== $drugProps) {
            $authRef = $drugProps->authorizationRef();
            $entity->setDrugAuthRef(null !== $authRef ? Uuid::fromString($authRef->toString()) : null);
            $entity->setDrugRequiresRx($drugProps->requiresPrescription());
            $entity->setDrugPrescriptionClass($drugProps->prescriptionClass()?->value);
            $entity->setDrugIsControlled($drugProps->isControlledSubstance());
        } else {
            $entity->setDrugAuthRef(null);
            $entity->setDrugRequiresRx(null);
            $entity->setDrugPrescriptionClass(null);
            $entity->setDrugIsControlled(null);
        }

        return $entity;
    }

    public function toDomain(ArticleEntity $entity): Article
    {
        $drugProperties = null;

        if (ArticleKind::DRUG === $entity->getKind()) {
            $authRef = $entity->getDrugAuthRef();

            $drugProperties = DrugProperties::create(
                authorizationRef: null !== $authRef
                    ? MarketingAuthorizationRef::fromString($authRef->toString())
                    : null,
                requiresPrescription: $entity->getDrugRequiresRx() ?? false,
                prescriptionClass: null !== $entity->getDrugPrescriptionClass()
                    ? PrescriptionClass::from($entity->getDrugPrescriptionClass())
                    : null,
                isControlledSubstance: $entity->getDrugIsControlled() ?? false,
            );
        }

        return Article::reconstitute(
            id: ArticleId::fromString($entity->getId()->toString()),
            clinicId: ClinicId::fromString($entity->getTenantId()->toString()),
            name: ArticleName::fromString($entity->getName()),
            code: ArticleCode::fromString($entity->getCode()),
            kind: $entity->getKind(),
            gtin: null !== $entity->getGtin() ? Gtin::fromString($entity->getGtin()) : null,
            taxCategory: TaxCategoryCode::fromString($entity->getTaxCategoryCode()),
            basePrice: Money::fromMinorUnits(
                $entity->getBasePriceMinorUnits(),
                CurrencyCode::fromString($entity->getBasePriceCurrency()),
            ),
            unitOfMeasure: UnitOfMeasure::fromString($entity->getUnitOfMeasure()),
            drugProperties: $drugProperties,
            trackStock: $entity->getTrackStock(),
            status: $entity->getStatus(),
            createdAt: $entity->getCreatedAt(),
            updatedAt: $entity->getUpdatedAt(),
        );
    }
}
