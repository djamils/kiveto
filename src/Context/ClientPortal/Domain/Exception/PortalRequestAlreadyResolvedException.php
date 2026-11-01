<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Exception;

/**
 * The request is no longer `submitted`: answered by someone else (whose
 * name is given, for the flash « Demande déjà traitée par {name} ») or
 * closed (no name).
 */
final class PortalRequestAlreadyResolvedException extends \DomainException
{
    public function __construct(
        public readonly string $requestId,
        public readonly string $status,
        public readonly ?string $resolvedByName,
    ) {
        parent::__construct(null === $resolvedByName ? 'Demande déjà close.' : \sprintf('Demande déjà traitée par %s.', $resolvedByName));
    }
}
