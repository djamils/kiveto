<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\Shared\ValueObject;

use App\Shared\Domain\Identifier\AbstractUuidId;

final class ArticleId extends AbstractUuidId
{
    public static function fromString(string $value): self
    {
        return new self($value);
    }
}
