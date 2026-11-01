<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\ListClinicsOpenToNewClients;

use App\Context\Scheduling\Domain\Booking\BookingMotif;
use App\Context\Scheduling\Domain\Booking\BookingMotifRepositoryInterface;
use App\Context\Scheduling\Domain\Booking\OnlineBookingSettingsRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListClinicsOpenToNewClientsHandler
{
    public function __construct(
        private OnlineBookingSettingsRepositoryInterface $settings,
        private BookingMotifRepositoryInterface $motifs,
    ) {
    }

    /**
     * @return list<string>
     */
    public function __invoke(ListClinicsOpenToNewClients $query): array
    {
        $open = [];

        foreach (array_unique($query->clinicIds) as $clinicId) {
            if ($this->isOpenToNewClients(ClinicId::fromString($clinicId))) {
                $open[] = $clinicId;
            }
        }

        return $open;
    }

    private function isOpenToNewClients(ClinicId $clinicId): bool
    {
        $settings = $this->settings->forClinic($clinicId);

        if (!$settings->isEnabled() || !$settings->isNewClientBookingEnabled()) {
            return false;
        }

        foreach ($this->motifs->listForClinic($clinicId, false) as $motif) {
            if (self::takesNewClients($motif)) {
                return true;
            }
        }

        return false;
    }

    private static function takesNewClients(BookingMotif $motif): bool
    {
        return $motif->isOnlineEnabled() && $motif->newClientsAllowed();
    }
}
