<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListBookableMotifs;

use App\Context\ClientPortal\Application\Port\BookingMotifsInterface;
use App\Context\ClientPortal\Application\Port\OnlineMotif;
use App\Context\ClientPortal\Application\Service\CareItems;
use App\Context\ClientPortal\Application\Service\CareItemStatus;
use App\Context\ClientPortal\Application\Service\OwnerPets;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * An overdue item outranks a due-soon one of the same kind. A booked item
 * suggests nothing: its appointment exists already.
 */
#[AsMessageHandler]
final readonly class ListBookableMotifsHandler
{
    /** By care kind: [overdue, due soon]. */
    private const array REASONS = [
        'vaccination'       => ['Rappel de vaccin en retard', 'Rappel de vaccin à prévoir'],
        'internal_parasite' => ['Vermifuge en retard', 'Vermifuge à prévoir'],
        'external_parasite' => ['Traitement antiparasitaire en retard', 'Traitement antiparasitaire à prévoir'],
        'other'             => ['Soin de prévention en retard', 'Soin de prévention à prévoir'],
    ];

    public function __construct(
        private OwnerPets $pets,
        private CareItems $careItems,
        private BookingMotifsInterface $motifs,
    ) {
    }

    /**
     * @return list<BookableMotif>
     */
    public function __invoke(ListBookableMotifs $query): array
    {
        $due = null === $query->petId ? [] : $this->dueKinds($query->userId, $query->clinicId, $query->petId);

        return array_map(
            static function (OnlineMotif $motif) use ($due): BookableMotif {
                $status = $due[$motif->preventiveCareKind ?? ''] ?? null;
                $reason = null === $status ? null : (self::REASONS[$motif->preventiveCareKind ?? ''] ?? self::REASONS['other'])[CareItemStatus::OVERDUE === $status ? 0 : 1];

                return new BookableMotif($motif->id, $motif->label, $motif->description, $motif->durationMin, null !== $reason, $reason);
            },
            $this->motifs->onlineMotifs($query->clinicId),
        );
    }

    /**
     * @return array<string, CareItemStatus> overdue or due soon, by care kind
     */
    private function dueKinds(string $userId, string $clinicId, string $petId): array
    {
        $kinds = [];

        foreach ($this->careItems->of($clinicId, [$this->pets->get($userId, $clinicId, $petId)]) as $item) {
            $status = $item->care->status;

            if (CareItemStatus::OVERDUE === $status || (CareItemStatus::DUE_SOON === $status && !isset($kinds[$item->care->kind]))) {
                $kinds[$item->care->kind] = $status;
            }
        }

        return $kinds;
    }
}
