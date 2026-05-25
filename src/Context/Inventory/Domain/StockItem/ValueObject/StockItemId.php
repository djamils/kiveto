<?php

declare(strict_types=1);

namespace App\Context\Inventory\Domain\StockItem\ValueObject;

use App\Shared\Domain\Identifier\AbstractUuidId;

final class StockItemId extends AbstractUuidId
{
    private const string UUIDV7_REGEX = '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    public static function fromString(string $value): self
    {
        if (1 !== preg_match(self::UUIDV7_REGEX, $value)) {
            throw new \InvalidArgumentException(\sprintf('Invalid StockItemId: "%s". Must be a valid UUID v7.', $value));
        }

        return new self($value);
    }
}
