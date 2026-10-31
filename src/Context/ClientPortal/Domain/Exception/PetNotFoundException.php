<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Exception;

/**
 * The pet is not one the owner account may see in that clinic (unknown,
 * archived, secondary owner, sold, given, another client's): a 404.
 */
final class PetNotFoundException extends \DomainException
{
    public function __construct(public readonly string $petId)
    {
        parent::__construct(\sprintf('Pet "%s" is not visible to the owner account.', $petId));
    }
}
