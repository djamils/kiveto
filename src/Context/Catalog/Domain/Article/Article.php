<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Article;

use App\Context\Catalog\Domain\Article\Event\ArticleArchived;
use App\Context\Catalog\Domain\Article\Event\ArticleBasePriceChanged;
use App\Context\Catalog\Domain\Article\Event\ArticleCreated;
use App\Context\Catalog\Domain\Article\Event\ArticlePrescriptionFlagsUpdated;
use App\Context\Catalog\Domain\Article\Event\ArticleRenamed;
use App\Context\Catalog\Domain\Article\Event\ArticleRestored;
use App\Context\Catalog\Domain\Article\Event\ArticleTaxCategoryChanged;
use App\Context\Catalog\Domain\Article\Exception\ArchivedArticleCannotBeModifiedException;
use App\Context\Catalog\Domain\Article\Exception\InvalidDrugPropertiesException;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleCode;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleId;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleKind;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleName;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleStatus;
use App\Context\Catalog\Domain\Article\ValueObject\DrugProperties;
use App\Context\Catalog\Domain\Article\ValueObject\Gtin;
use App\Context\Catalog\Domain\Article\ValueObject\PrescriptionClass;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;
use App\Shared\Money\Domain\ValueObject\Money;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;

final class Article extends AggregateRoot
{
    private function __construct(
        private ArticleId $id,
        private ClinicId $clinicId,
        private ArticleName $name,
        private ArticleCode $code,
        private ArticleKind $kind,
        private ?Gtin $gtin,
        private TaxCategoryCode $taxCategory,
        private Money $basePrice,
        private UnitOfMeasure $unitOfMeasure,
        private ?DrugProperties $drugProperties,
        private bool $trackStock,
        private ArticleStatus $status,
        private \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function createDrug(
        ArticleId $id,
        ClinicId $clinicId,
        ArticleName $name,
        ArticleCode $code,
        ?Gtin $gtin,
        TaxCategoryCode $taxCategory,
        Money $basePrice,
        UnitOfMeasure $unitOfMeasure,
        DrugProperties $drugProperties,
        bool $trackStock,
        \DateTimeImmutable $createdAt,
    ): self {
        $article = new self(
            id: $id,
            clinicId: $clinicId,
            name: $name,
            code: $code,
            kind: ArticleKind::DRUG,
            gtin: $gtin,
            taxCategory: $taxCategory,
            basePrice: $basePrice,
            unitOfMeasure: $unitOfMeasure,
            drugProperties: $drugProperties,
            trackStock: $trackStock,
            status: ArticleStatus::ACTIVE,
            createdAt: $createdAt,
            updatedAt: $createdAt,
        );

        $article->recordDomainEvent(new ArticleCreated(
            articleId: $id->toString(),
            clinicId: $clinicId->toString(),
            name: $name->toString(),
            code: $code->toString(),
            kind: ArticleKind::DRUG->value,
            gtin: $gtin?->toString(),
            taxCategoryCode: $taxCategory->toString(),
            basePriceMinorUnits: $basePrice->minorUnits(),
            basePriceCurrency: $basePrice->currency()->toString(),
            unitOfMeasure: $unitOfMeasure->toString(),
            trackStock: $trackStock,
        ));

        return $article;
    }

    public static function createNonDrug(
        ArticleId $id,
        ClinicId $clinicId,
        ArticleName $name,
        ArticleCode $code,
        ArticleKind $kind,
        ?Gtin $gtin,
        TaxCategoryCode $taxCategory,
        Money $basePrice,
        UnitOfMeasure $unitOfMeasure,
        bool $trackStock,
        \DateTimeImmutable $createdAt,
    ): self {
        if (ArticleKind::DRUG === $kind) {
            throw new InvalidDrugPropertiesException('Use createDrug() for DRUG articles.');
        }

        $article = new self(
            id: $id,
            clinicId: $clinicId,
            name: $name,
            code: $code,
            kind: $kind,
            gtin: $gtin,
            taxCategory: $taxCategory,
            basePrice: $basePrice,
            unitOfMeasure: $unitOfMeasure,
            drugProperties: null,
            trackStock: $trackStock,
            status: ArticleStatus::ACTIVE,
            createdAt: $createdAt,
            updatedAt: $createdAt,
        );

        $article->recordDomainEvent(new ArticleCreated(
            articleId: $id->toString(),
            clinicId: $clinicId->toString(),
            name: $name->toString(),
            code: $code->toString(),
            kind: $kind->value,
            gtin: $gtin?->toString(),
            taxCategoryCode: $taxCategory->toString(),
            basePriceMinorUnits: $basePrice->minorUnits(),
            basePriceCurrency: $basePrice->currency()->toString(),
            unitOfMeasure: $unitOfMeasure->toString(),
            trackStock: $trackStock,
        ));

        return $article;
    }

    public static function reconstitute(
        ArticleId $id,
        ClinicId $clinicId,
        ArticleName $name,
        ArticleCode $code,
        ArticleKind $kind,
        ?Gtin $gtin,
        TaxCategoryCode $taxCategory,
        Money $basePrice,
        UnitOfMeasure $unitOfMeasure,
        ?DrugProperties $drugProperties,
        bool $trackStock,
        ArticleStatus $status,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            clinicId: $clinicId,
            name: $name,
            code: $code,
            kind: $kind,
            gtin: $gtin,
            taxCategory: $taxCategory,
            basePrice: $basePrice,
            unitOfMeasure: $unitOfMeasure,
            drugProperties: $drugProperties,
            trackStock: $trackStock,
            status: $status,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    public function rename(ArticleName $name, \DateTimeImmutable $updatedAt): void
    {
        if (ArticleStatus::ARCHIVED === $this->status) {
            throw new ArchivedArticleCannotBeModifiedException($this->id->toString());
        }

        $this->name      = $name;
        $this->updatedAt = $updatedAt;

        $this->recordDomainEvent(new ArticleRenamed(
            articleId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            newName: $name->toString(),
        ));
    }

    public function changeBasePrice(Money $basePrice, \DateTimeImmutable $updatedAt): void
    {
        if (ArticleStatus::ARCHIVED === $this->status) {
            throw new ArchivedArticleCannotBeModifiedException($this->id->toString());
        }

        $this->basePrice = $basePrice;
        $this->updatedAt = $updatedAt;

        $this->recordDomainEvent(new ArticleBasePriceChanged(
            articleId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            newPriceMinorUnits: $basePrice->minorUnits(),
            newPriceCurrency: $basePrice->currency()->toString(),
        ));
    }

    public function changeTaxCategory(TaxCategoryCode $taxCategory, \DateTimeImmutable $updatedAt): void
    {
        if (ArticleStatus::ARCHIVED === $this->status) {
            throw new ArchivedArticleCannotBeModifiedException($this->id->toString());
        }

        $this->taxCategory = $taxCategory;
        $this->updatedAt   = $updatedAt;

        $this->recordDomainEvent(new ArticleTaxCategoryChanged(
            articleId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            newTaxCategoryCode: $taxCategory->toString(),
        ));
    }

    public function updatePrescriptionFlags(
        bool $requiresPrescription,
        ?PrescriptionClass $prescriptionClass,
        bool $isControlledSubstance,
        \DateTimeImmutable $updatedAt,
    ): void {
        if (ArticleKind::DRUG !== $this->kind) {
            throw new InvalidDrugPropertiesException('Prescription flags can only be updated on DRUG articles.');
        }

        $existing = $this->drugProperties;

        // Idempotency: only mutate if flags actually changed
        $unchanged = null !== $existing
            && $existing->requiresPrescription() === $requiresPrescription
            && $existing->prescriptionClass() === $prescriptionClass
            && $existing->isControlledSubstance() === $isControlledSubstance;

        if ($unchanged) {
            return;
        }

        $this->drugProperties = ($existing ?? DrugProperties::create(null, false, null, false))
            ->withUpdatedFlags($requiresPrescription, $prescriptionClass, $isControlledSubstance)
        ;
        $this->updatedAt = $updatedAt;

        $this->recordDomainEvent(new ArticlePrescriptionFlagsUpdated(
            articleId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            requiresPrescription: $requiresPrescription,
            prescriptionClass: $prescriptionClass?->value,
            isControlledSubstance: $isControlledSubstance,
        ));
    }

    public function archive(\DateTimeImmutable $updatedAt): void
    {
        if (ArticleStatus::ARCHIVED === $this->status) {
            return;
        }

        $this->status    = ArticleStatus::ARCHIVED;
        $this->updatedAt = $updatedAt;

        $this->recordDomainEvent(new ArticleArchived(
            articleId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
        ));
    }

    public function restore(\DateTimeImmutable $updatedAt): void
    {
        if (ArticleStatus::ACTIVE === $this->status) {
            return;
        }

        $this->status    = ArticleStatus::ACTIVE;
        $this->updatedAt = $updatedAt;

        $this->recordDomainEvent(new ArticleRestored(
            articleId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
        ));
    }

    public function id(): ArticleId
    {
        return $this->id;
    }

    public function clinicId(): ClinicId
    {
        return $this->clinicId;
    }

    public function name(): ArticleName
    {
        return $this->name;
    }

    public function code(): ArticleCode
    {
        return $this->code;
    }

    public function kind(): ArticleKind
    {
        return $this->kind;
    }

    public function gtin(): ?Gtin
    {
        return $this->gtin;
    }

    public function taxCategory(): TaxCategoryCode
    {
        return $this->taxCategory;
    }

    public function basePrice(): Money
    {
        return $this->basePrice;
    }

    public function unitOfMeasure(): UnitOfMeasure
    {
        return $this->unitOfMeasure;
    }

    public function drugProperties(): ?DrugProperties
    {
        return $this->drugProperties;
    }

    public function trackStock(): bool
    {
        return $this->trackStock;
    }

    public function status(): ArticleStatus
    {
        return $this->status;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
