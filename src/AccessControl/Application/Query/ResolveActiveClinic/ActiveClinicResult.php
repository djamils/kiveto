<?php

declare(strict_types=1);

namespace App\AccessControl\Application\Query\ResolveActiveClinic;

use App\AccessControl\Application\Query\ListClinicsForUser\AccessibleClinic;

final readonly class ActiveClinicResult
{
    /**
     * @param AccessibleClinic[] $clinics
     */
    private function __construct(
        public ActiveClinicResultType $type,
        public ?AccessibleClinic $clinic,
        public array $clinics,
    ) {
    }

    public static function none(): self
    {
        return new self(
            type: ActiveClinicResultType::NONE,
            clinic: null,
            clinics: [],
        );
    }

    public static function single(AccessibleClinic $clinic): self
    {
        return new self(
            type: ActiveClinicResultType::SINGLE,
            clinic: $clinic,
            clinics: [$clinic],
        );
    }

    /**
     * @param AccessibleClinic[] $clinics
     */
    public static function multiple(array $clinics): self
    {
        return new self(
            type: ActiveClinicResultType::MULTIPLE,
            clinic: null,
            clinics: $clinics,
        );
    }
}
