<?php

declare(strict_types=1);

namespace App\Context\Billing\Infrastructure\Adapter\Client;

use App\Context\Billing\Application\Port\PayerDirectoryInterface;
use App\Context\Billing\Application\Port\PayerSnapshotDto;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * Finds who pays for a visit: admission → patient → animal → primary owner.
 *
 * The chain crosses four contexts, none of which exposes it as a single query,
 * so it is read here as one projection rather than through four round trips. It
 * reads the ownership and client rows rather than the animal search index on
 * purpose: that index is rebuilt after the fact, and an invoice has to be able
 * to name its payer the moment the visit is charged.
 *
 * An intake with no identified animal, or an animal with no active owner,
 * simply has no payer yet. A structure is billed under its legal name, to the
 * attention of its contact person.
 */
final readonly class DbalPayerDirectory implements PayerDirectoryInterface
{
    /** Civility as stored by the Client context => honorific printed before the name. */
    private const array HONORIFICS = ['madam' => 'Mme', 'mister' => 'M.'];

    public function __construct(private Connection $connection)
    {
    }

    public function forAdmission(string $admissionId, string $clinicId): ?PayerSnapshotDto
    {
        $row = $this->connection->fetchAssociative(
            "SELECT BIN_TO_UUID(c.id) AS client_id, c.first_name, c.last_name, c.civility, c.legal_name,
                    c.postal_address_street_line_1, c.postal_address_street_line_2,
                    c.postal_address_postal_code, c.postal_address_city
             FROM admission__admissions a
             INNER JOIN patient__patients p ON p.id = a.patient_id
             INNER JOIN animal__ownerships o ON o.animal_id = p.animal_link_id
                 AND o.role = 'primary' AND o.status = 'active'
             INNER JOIN client__clients c ON c.id = o.client_id
             WHERE a.id = :admissionId AND a.clinic_id = :clinicId
             ORDER BY o.started_at ASC
             LIMIT 1",
            [
                'admissionId' => Uuid::fromString($admissionId)->toBinary(),
                'clinicId'    => Uuid::fromString($clinicId)->toBinary(),
            ],
        );

        return false === $row ? null : $this->snapshot($row);
    }

    public function forClient(string $clientId, string $clinicId): ?PayerSnapshotDto
    {
        $row = $this->connection->fetchAssociative(
            'SELECT BIN_TO_UUID(c.id) AS client_id, c.first_name, c.last_name, c.civility, c.legal_name,
                    c.postal_address_street_line_1, c.postal_address_street_line_2,
                    c.postal_address_postal_code, c.postal_address_city
             FROM client__clients c
             WHERE c.id = :clientId AND c.clinic_id = :clinicId',
            [
                'clientId' => Uuid::fromString($clientId)->toBinary(),
                'clinicId' => Uuid::fromString($clinicId)->toBinary(),
            ],
        );

        return false === $row ? null : $this->snapshot($row);
    }

    public function clientWithPhone(string $phone, string $clinicId): ?string
    {
        $id = $this->connection->fetchOne(
            "SELECT BIN_TO_UUID(c.id)
             FROM client__clients c
             INNER JOIN client__contact_methods cm ON cm.client_id = c.id AND cm.type = 'phone'
             WHERE c.clinic_id = :clinicId AND c.status = 'active' AND cm.value = :phone
             ORDER BY c.created_at ASC
             LIMIT 1",
            [
                'clinicId' => Uuid::fromString($clinicId)->toBinary(),
                'phone'    => $phone,
            ],
        );

        return \is_string($id) ? $id : null;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function snapshot(array $row): ?PayerSnapshotDto
    {
        $person = mb_trim(
            RowAccessor::string($row, 'first_name') . ' ' . RowAccessor::string($row, 'last_name'),
        );

        $legalName = mb_trim(RowAccessor::nullableString($row, 'legal_name') ?? '');

        if ('' !== $legalName) {
            $honorific = self::HONORIFICS[RowAccessor::string($row, 'civility')] ?? '';

            return new PayerSnapshotDto(
                clientId: RowAccessor::string($row, 'client_id'),
                displayName: $legalName,
                addressLines: $this->addressOf($row),
                attention: '' === $person ? null : mb_trim($honorific . ' ' . $person),
            );
        }

        if ('' === $person) {
            return null;
        }

        return new PayerSnapshotDto(
            clientId: RowAccessor::string($row, 'client_id'),
            displayName: $person,
            addressLines: $this->addressOf($row),
        );
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return list<string>
     */
    private function addressOf(array $row): array
    {
        $lines = [];

        foreach (['postal_address_street_line_1', 'postal_address_street_line_2'] as $column) {
            $line = RowAccessor::nullableString($row, $column);

            if (null !== $line && '' !== mb_trim($line)) {
                $lines[] = mb_trim($line);
            }
        }

        $zip  = RowAccessor::nullableString($row, 'postal_address_postal_code');
        $city = RowAccessor::nullableString($row, 'postal_address_city');

        $cityLine = mb_trim(($zip ?? '') . ' ' . ($city ?? ''));

        if ('' !== $cityLine) {
            $lines[] = $cityLine;
        }

        return $lines;
    }
}
