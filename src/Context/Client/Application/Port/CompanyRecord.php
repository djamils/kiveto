<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Port;

/**
 * What the registry knows of a SIREN for its verification. A confidential
 * (non-diffusible) company is known but its name is masked.
 */
final readonly class CompanyRecord
{
    public function __construct(
        public string $siren,
        public ?string $legalName,
        public bool $isActive,
        public bool $isConfidential,
        public ?string $ceasedOn = null,
    ) {
    }
}
