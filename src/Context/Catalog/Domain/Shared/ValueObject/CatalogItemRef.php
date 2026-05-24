<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Shared\ValueObject;

use App\Context\Catalog\Domain\Act\ValueObject\ActId;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleId;

final class CatalogItemRef
{
    private function __construct(
        private readonly CatalogItemType $type,
        private readonly string $id,
    ) {
    }

    public static function toAct(ActId $actId): self
    {
        return new self(CatalogItemType::ACT, $actId->toString());
    }

    public static function toArticle(ArticleId $articleId): self
    {
        return new self(CatalogItemType::ARTICLE, $articleId->toString());
    }

    public static function fromPrimitives(string $type, string $id): self
    {
        return new self(CatalogItemType::from($type), $id);
    }

    public function type(): CatalogItemType
    {
        return $this->type;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function isAct(): bool
    {
        return CatalogItemType::ACT === $this->type;
    }

    public function isArticle(): bool
    {
        return CatalogItemType::ARTICLE === $this->type;
    }

    public function equals(self $other): bool
    {
        return $this->type === $other->type && $this->id === $other->id;
    }
}
