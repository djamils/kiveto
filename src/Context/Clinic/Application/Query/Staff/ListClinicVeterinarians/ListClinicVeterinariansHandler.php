<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians;

use App\Context\Clinic\Application\Port\ClinicMembershipReadRepositoryInterface;
use App\Context\Clinic\Application\Port\StaffProfileReadRepositoryInterface;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListClinicVeterinariansHandler
{
    public function __construct(
        private ClinicMembershipReadRepositoryInterface $readRepository,
        private StaffProfileReadRepositoryInterface $profileReadRepository,
    ) {
    }

    /**
     * @return list<ClinicVeterinarianItem>
     */
    public function __invoke(ListClinicVeterinarians $query): array
    {
        $items = $this->readRepository->findVeterinariansForClinic(
            ClinicId::fromString($query->clinicId),
        );

        if ([] === $items) {
            return [];
        }

        $membershipIds = array_map(
            static fn (ClinicVeterinarianItem $item): string => $item->membershipId,
            $items,
        );

        $profileMap = $this->profileReadRepository->findByMembershipIds($membershipIds);

        return array_map(
            static function (ClinicVeterinarianItem $item) use ($profileMap): ClinicVeterinarianItem {
                $profile = $profileMap[$item->membershipId] ?? null;

                return new ClinicVeterinarianItem(
                    userId: $item->userId,
                    role: $item->role,
                    engagement: $item->engagement,
                    membershipId: $item->membershipId,
                    displayName: $profile?->displayName,
                    professionalTitle: $profile?->professionalTitle,
                    agendaColor: $profile?->agendaColor,
                    sortOrder: $profile?->sortOrder,
                    isVisibleInAgenda: $profile?->isVisibleInAgenda,
                );
            },
            $items,
        );
    }
}
