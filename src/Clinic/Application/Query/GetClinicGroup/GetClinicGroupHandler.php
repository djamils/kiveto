<?php

declare(strict_types=1);

namespace App\Clinic\Application\Query\GetClinicGroup;

use App\Clinic\Domain\Repository\ClinicGroupRepositoryInterface;
use App\Clinic\Domain\ValueObject\ClinicGroupId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetClinicGroupHandler
{
    public function __construct(
        private ClinicGroupRepositoryInterface $clinicGroupRepository,
    ) {
    }

    public function __invoke(GetClinicGroup $query): ?ClinicGroupDto
    {
        $clinicGroupId = ClinicGroupId::fromString($query->clinicGroupId);
        $clinicGroup   = $this->clinicGroupRepository->findById($clinicGroupId);

        if (null === $clinicGroup) {
            return null;
        }

        return new ClinicGroupDto(
            id: $clinicGroup->id()->toString(),
            name: $clinicGroup->name(),
            status: $clinicGroup->status(),
            createdAt: $clinicGroup->createdAt()->format('c'),
        );
    }
}
