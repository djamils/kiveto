<?php

declare(strict_types=1);

namespace App\System\Taxation\Infrastructure\Persistence\Doctrine\Mapper;

use App\System\Taxation\Domain\TaxCategory;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use App\System\Taxation\Infrastructure\Persistence\Doctrine\Entity\TaxCategoryEntity;

final readonly class TaxCategoryMapper
{
    public function toDomain(TaxCategoryEntity $entity): TaxCategory
    {
        return TaxCategory::reconstitute(
            TaxCategoryCode::fromString($entity->getCode()),
            $entity->getDisplayName(),
            $entity->getDescription(),
            $entity->isActive(),
            $entity->getCreatedAt(),
            $entity->getUpdatedAt(),
        );
    }

    public function toEntity(TaxCategory $category): TaxCategoryEntity
    {
        $entity = new TaxCategoryEntity();
        $entity->setCode($category->code()->toString());
        $entity->setDisplayName($category->displayName());
        $entity->setDescription($category->description());
        $entity->setActive($category->isActive());
        $entity->setCreatedAt($category->createdAt());
        $entity->setUpdatedAt($category->updatedAt());

        return $entity;
    }
}
