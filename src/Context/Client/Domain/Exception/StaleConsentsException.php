<?php

declare(strict_types=1);

namespace App\Context\Client\Domain\Exception;

/**
 * The consents were saved from a sheet rendered before another change of
 * the client (a Portal withdrawal, another user): the save is refused.
 */
final class StaleConsentsException extends \DomainException
{
    public function __construct(string $clientId, int $expectedVersion, int $currentVersion)
    {
        parent::__construct(\sprintf(
            'Client "%s" changed since its consents were read (version %d, now %d).',
            $clientId,
            $expectedVersion,
            $currentVersion,
        ));
    }
}
