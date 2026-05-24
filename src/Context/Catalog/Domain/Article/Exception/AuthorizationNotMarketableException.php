<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Article\Exception;

final class AuthorizationNotMarketableException extends \DomainException
{
    public function __construct(string $authorizationRef)
    {
        parent::__construct(\sprintf(
            'Marketing authorization "%s" is not marketable (withdrawn or not found).',
            $authorizationRef,
        ));
    }
}
