<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\Clinic\GetClinic;

use App\Context\Clinic\Domain\Repository\ClinicRepositoryInterface;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetClinicHandler
{
    public function __construct(
        private ClinicRepositoryInterface $clinicRepository,
    ) {
    }

    public function __invoke(GetClinic $query): ?ClinicDto
    {
        $clinicId = ClinicId::fromString($query->clinicId);
        $clinic   = $this->clinicRepository->findById($clinicId);

        if (null === $clinic) {
            return null;
        }

        return new ClinicDto(
            id: $clinic->id()->toString(),
            name: $clinic->name(),
            slug: $clinic->slug()->toString(),
            timeZone: $clinic->timeZone()->toString(),
            locale: $clinic->locale()->toString(),
            countryCode: $clinic->countryCode()->toString(),
            jurisdictionCode: $clinic->jurisdictionCode(),
            currencyCode: $clinic->currencyCode()->toString(),
            status: $clinic->status(),
            clinicGroupId: $clinic->clinicGroupId()?->toString(),
            createdAt: $clinic->createdAt()->format('c'),
            updatedAt: $clinic->updatedAt()->format('c'),
        );
    }
}
