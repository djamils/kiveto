<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Exception;

/** The owner accepted another version of the terms of use than the current one. */
final class TermsOutdatedException extends \DomainException
{
    public function __construct(
        public readonly string $acceptedVersion,
        public readonly string $currentVersion,
    ) {
        parent::__construct(\sprintf('Terms version "%s" is not the current one ("%s").', $acceptedVersion, $currentVersion));
    }
}
