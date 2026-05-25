<?php

declare(strict_types=1);

namespace App\Context\Inventory\Domain\StockItem\Exception;

final class DuplicateStockItemException extends \DomainException
{
    public function __construct(string $clinicId, string $articleId)
    {
        parent::__construct(\sprintf('A StockItem for article "%s" in clinic "%s" already exists.', $articleId, $clinicId));
    }
}
