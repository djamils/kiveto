<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Act;

use App\Context\Catalog\Domain\Act\Event\ActArchived;
use App\Context\Catalog\Domain\Act\Event\ActBasePriceChanged;
use App\Context\Catalog\Domain\Act\Event\ActCreated;
use App\Context\Catalog\Domain\Act\Event\ActRenamed;
use App\Context\Catalog\Domain\Act\Event\ActRestored;
use App\Context\Catalog\Domain\Act\Event\ActTaxCategoryChanged;
use App\Context\Catalog\Domain\Act\Exception\ArchivedActCannotBeModifiedException;
use App\Context\Catalog\Domain\Act\ValueObject\ActCategory;
use App\Context\Catalog\Domain\Act\ValueObject\ActCode;
use App\Context\Catalog\Domain\Act\ValueObject\ActDescription;
use App\Context\Catalog\Domain\Act\ValueObject\ActDuration;
use App\Context\Catalog\Domain\Act\ValueObject\ActId;
use App\Context\Catalog\Domain\Act\ValueObject\ActName;
use App\Context\Catalog\Domain\Act\ValueObject\ActStatus;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Money\Domain\ValueObject\Money;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;

final class Act extends AggregateRoot
{
    private function __construct(
        private ActId $id,
        private ClinicId $clinicId,
        private ActName $name,
        private ActCode $code,
        private ?ActDescription $description,
        private ActCategory $category,
        private TaxCategoryCode $taxCategory,
        private Money $basePrice,
        private ActDuration $estimatedDuration,
        private bool $requiresAnesthesia,
        private ActStatus $status,
        private \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function create(
        ActId $id,
        ClinicId $clinicId,
        ActName $name,
        ActCode $code,
        ?ActDescription $description,
        ActCategory $category,
        TaxCategoryCode $taxCategory,
        Money $basePrice,
        ActDuration $estimatedDuration,
        bool $requiresAnesthesia,
        \DateTimeImmutable $createdAt,
    ): self {
        $act = new self(
            id: $id,
            clinicId: $clinicId,
            name: $name,
            code: $code,
            description: $description,
            category: $category,
            taxCategory: $taxCategory,
            basePrice: $basePrice,
            estimatedDuration: $estimatedDuration,
            requiresAnesthesia: $requiresAnesthesia,
            status: ActStatus::ACTIVE,
            createdAt: $createdAt,
            updatedAt: $createdAt,
        );

        $act->recordDomainEvent(new ActCreated(
            actId: $id->toString(),
            clinicId: $clinicId->toString(),
            name: $name->toString(),
            code: $code->toString(),
            category: $category->value,
            taxCategoryCode: $taxCategory->toString(),
            basePriceMinorUnits: $basePrice->minorUnits(),
            basePriceCurrency: $basePrice->currency()->toString(),
            estimatedDurationMinutes: $estimatedDuration->toMinutes(),
            requiresAnesthesia: $requiresAnesthesia,
        ));

        return $act;
    }

    public static function reconstitute(
        ActId $id,
        ClinicId $clinicId,
        ActName $name,
        ActCode $code,
        ?ActDescription $description,
        ActCategory $category,
        TaxCategoryCode $taxCategory,
        Money $basePrice,
        ActDuration $estimatedDuration,
        bool $requiresAnesthesia,
        ActStatus $status,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            clinicId: $clinicId,
            name: $name,
            code: $code,
            description: $description,
            category: $category,
            taxCategory: $taxCategory,
            basePrice: $basePrice,
            estimatedDuration: $estimatedDuration,
            requiresAnesthesia: $requiresAnesthesia,
            status: $status,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    public function rename(ActName $name, \DateTimeImmutable $updatedAt): void
    {
        if (ActStatus::ARCHIVED === $this->status) {
            throw new ArchivedActCannotBeModifiedException($this->id->toString());
        }

        $this->name      = $name;
        $this->updatedAt = $updatedAt;

        $this->recordDomainEvent(new ActRenamed(
            actId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            newName: $name->toString(),
        ));
    }

    public function changeBasePrice(Money $basePrice, \DateTimeImmutable $updatedAt): void
    {
        if (ActStatus::ARCHIVED === $this->status) {
            throw new ArchivedActCannotBeModifiedException($this->id->toString());
        }

        $this->basePrice = $basePrice;
        $this->updatedAt = $updatedAt;

        $this->recordDomainEvent(new ActBasePriceChanged(
            actId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            newPriceMinorUnits: $basePrice->minorUnits(),
            newPriceCurrency: $basePrice->currency()->toString(),
        ));
    }

    public function changeTaxCategory(TaxCategoryCode $taxCategory, \DateTimeImmutable $updatedAt): void
    {
        if (ActStatus::ARCHIVED === $this->status) {
            throw new ArchivedActCannotBeModifiedException($this->id->toString());
        }

        $this->taxCategory = $taxCategory;
        $this->updatedAt   = $updatedAt;

        $this->recordDomainEvent(new ActTaxCategoryChanged(
            actId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            newTaxCategoryCode: $taxCategory->toString(),
        ));
    }

    public function archive(\DateTimeImmutable $updatedAt): void
    {
        if (ActStatus::ARCHIVED === $this->status) {
            return;
        }

        $this->status    = ActStatus::ARCHIVED;
        $this->updatedAt = $updatedAt;

        $this->recordDomainEvent(new ActArchived(
            actId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
        ));
    }

    public function restore(\DateTimeImmutable $updatedAt): void
    {
        if (ActStatus::ACTIVE === $this->status) {
            return;
        }

        $this->status    = ActStatus::ACTIVE;
        $this->updatedAt = $updatedAt;

        $this->recordDomainEvent(new ActRestored(
            actId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
        ));
    }

    public function id(): ActId
    {
        return $this->id;
    }

    public function clinicId(): ClinicId
    {
        return $this->clinicId;
    }

    public function name(): ActName
    {
        return $this->name;
    }

    public function code(): ActCode
    {
        return $this->code;
    }

    public function description(): ?ActDescription
    {
        return $this->description;
    }

    public function category(): ActCategory
    {
        return $this->category;
    }

    public function taxCategory(): TaxCategoryCode
    {
        return $this->taxCategory;
    }

    public function basePrice(): Money
    {
        return $this->basePrice;
    }

    public function estimatedDuration(): ActDuration
    {
        return $this->estimatedDuration;
    }

    public function requiresAnesthesia(): bool
    {
        return $this->requiresAnesthesia;
    }

    public function status(): ActStatus
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
