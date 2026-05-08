<?php

declare(strict_types=1);

namespace App\Context\Regulatory\Domain;

interface JurisdictionResolverInterface
{
    public function resolveForClinic(string $clinicId): string;
}
