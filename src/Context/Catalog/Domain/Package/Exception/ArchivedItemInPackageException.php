<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Package\Exception;

final class ArchivedItemInPackageException extends \DomainException
{
    public function __construct(string $itemType, string $itemId)
    {
        parent::__construct(\sprintf(
            'Cannot add archived item "%s/%s" to a package.',
            $itemType,
            $itemId,
        ));
    }
}
