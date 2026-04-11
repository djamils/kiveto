<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians;

use App\Context\Clinic\Application\Port\ClinicMembershipReadRepositoryInterface;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListClinicVeterinariansHandler
{
    public function __construct(
        private ClinicMembershipReadRepositoryInterface $readRepository,
    ) {
    }

    /**
     * @return list<ClinicVeterinarianItem>
     */
    public function __invoke(ListClinicVeterinarians $query): array
    {
        return $this->readRepository->findVeterinariansForClinic(
            ClinicId::fromString($query->clinicId),
        );
    }
}
