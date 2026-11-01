<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\ListClientsByMobilePhone;

use App\Context\Client\Application\Port\ClientAnimalNamesInterface;
use App\Context\Client\Application\Port\ClientReadRepositoryInterface;
use App\Context\Client\Application\Port\ClinicStatusInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * The read model finds the non-archived files; the clinics' status comes
 * from the Clinic context (port), the animal names from the Animal context
 * (port), read once per clinic.
 */
#[AsMessageHandler]
final readonly class ListClientsByMobilePhoneHandler
{
    public function __construct(
        private ClientReadRepositoryInterface $clientReadRepository,
        private ClinicStatusInterface $clinics,
        private ClientAnimalNamesInterface $animals,
    ) {
    }

    /**
     * @return list<MobileClientMatch>
     */
    public function __invoke(ListClientsByMobilePhone $query): array
    {
        $phone = trim($query->phone);

        if ('' === $phone) {
            return [];
        }

        $rows = $this->clientReadRepository->findByMobilePhone($phone);

        if ([] === $rows) {
            return [];
        }

        $active   = $this->clinics->activeClinicIds(array_values(array_unique(array_column($rows, 'clinicId'))));
        $byClinic = [];

        foreach ($rows as $row) {
            if (\in_array($row['clinicId'], $active, true)) {
                $byClinic[$row['clinicId']][] = $row;
            }
        }

        $matches = [];

        foreach ($byClinic as $clinicId => $clinicRows) {
            $names = $this->animals->namesPerClient((string) $clinicId, array_column($clinicRows, 'clientId'));

            foreach ($clinicRows as $row) {
                $matches[] = new MobileClientMatch($row['clinicId'], $row['clientId'], $row['firstName'], $row['lastName'], $names[$row['clientId']] ?? []);
            }
        }

        return $matches;
    }
}
