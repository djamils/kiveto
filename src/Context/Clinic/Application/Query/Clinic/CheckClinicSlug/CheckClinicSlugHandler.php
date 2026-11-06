<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\Clinic\CheckClinicSlug;

use App\Context\Clinic\Domain\Repository\ClinicRepositoryInterface;
use App\Context\Clinic\Domain\ValueObject\ClinicSlug;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CheckClinicSlugHandler
{
    public function __construct(
        private ClinicRepositoryInterface $clinics,
    ) {
    }

    public function __invoke(CheckClinicSlug $query): ClinicSlugCheck
    {
        try {
            $slug = ClinicSlug::fromString($query->slug);
        } catch (\InvalidArgumentException) {
            return ClinicSlugCheck::INVALID;
        }

        return $this->clinics->existsBySlug($slug) ? ClinicSlugCheck::TAKEN : ClinicSlugCheck::AVAILABLE;
    }
}
