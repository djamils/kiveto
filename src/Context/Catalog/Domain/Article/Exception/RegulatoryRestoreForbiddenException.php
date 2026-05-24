<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Article\Exception;

final class RegulatoryRestoreForbiddenException extends \DomainException
{
    public function __construct(string $articleId, string $authorizationRef)
    {
        parent::__construct(\sprintf(
            'Cannot restore article "%s": marketing authorization "%s" is withdrawn.',
            $articleId,
            $authorizationRef,
        ));
    }
}
