<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\ListBookingMotifs;

use App\Context\Scheduling\Domain\Booking\BookingMotif;
use App\Context\Scheduling\Domain\Booking\BookingMotifRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockType;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListBookingMotifsHandler
{
    public function __construct(private BookingMotifRepositoryInterface $motifs)
    {
    }

    /**
     * @return list<BookingMotifView>
     */
    public function __invoke(ListBookingMotifs $query): array
    {
        return array_map(
            static fn (BookingMotif $motif): BookingMotifView => new BookingMotifView(
                id: $motif->id()->toString(),
                label: $motif->label(),
                description: $motif->description(),
                durationMin: $motif->durationMin(),
                onlineEnabled: $motif->isOnlineEnabled(),
                confirmationKnownClient: $motif->confirmationKnownClient()->value,
                allowedBlockTypes: array_map(static fn (PlanningBlockType $type): string => $type->value, $motif->allowedBlockTypes()),
                practitionerIds: array_map(static fn (UserId $id): string => $id->toString(), $motif->practitionerIds()),
                preventiveCareKind: $motif->preventiveCareKind()?->value,
                newClientsAllowed: $motif->newClientsAllowed(),
                sortOrder: $motif->sortOrder(),
                archivedAt: $motif->archivedAt(),
            ),
            $this->motifs->listForClinic(ClinicId::fromString($query->clinicId), $query->includeArchived),
        );
    }
}
