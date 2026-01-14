<?php

declare(strict_types=1);

namespace App\AccessControl\Application\Query\ResolveClinicSelectionForUser;

use App\AccessControl\Application\Query\ListClinicsForUser\AccessibleClinic;

final readonly class ClinicSelectionDecision
{
    /**
     * @param AccessibleClinic[] $clinics
     */
    private function __construct(
        public ClinicSelectionType $type,
        public ?AccessibleClinic $singleClinic,
        public array $clinics,
    ) {
    }

    public static function none(): self
    {
        return new self(
            type: ClinicSelectionType::NONE,
            singleClinic: null,
            clinics: [],
        );
    }

    public static function single(AccessibleClinic $clinic): self
    {
        return new self(
            type: ClinicSelectionType::SINGLE,
            singleClinic: $clinic,
            clinics: [$clinic],
        );
    }

    /**
     * @param AccessibleClinic[] $clinics
     */
    public static function multiple(array $clinics): self
    {
        return new self(
            type: ClinicSelectionType::MULTIPLE,
            singleClinic: null,
            clinics: $clinics,
        );
    }
}
