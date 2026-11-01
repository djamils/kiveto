<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\ClientPortal\Application\Port\PortalRequestReadModelInterface;
use App\Context\ClientPortal\Application\Port\PortalRequestRecord;
use App\Context\ClientPortal\Application\Port\RequestPosition;
use App\Context\ClientPortal\Domain\Link\ClientLinkStatus;
use App\Context\ClientPortal\Domain\Request\PortalRequestKind;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

/** Both request tables in one `UNION ALL`, paged on `(submitted_at, id)`. */
final readonly class DbalPortalRequestReadModel implements PortalRequestReadModelInterface
{
    private const string CHANGES  = DbalPetChangeRequestRepository::TABLE;
    private const string RENEWALS = DbalPrescriptionRenewalRequestRepository::TABLE;
    private const string LINKS    = 'client_portal__client_links';

    private const string COMMON = 'id, clinic_id, client_id, user_id, pet_id, note, status, submitted_at, resolved_at, resolved_by_name,'
        . ' resolution_message, client_unreachable, closed_reason';

    private const string AFTER = ' AND (submitted_at < :at OR (submitted_at = :at AND id < :after))';

    public function __construct(private Connection $connection)
    {
    }

    public function ownerPage(string $userId, string $clinicId, ?RequestPosition $after, int $limit): array
    {
        $scope = 'user_id = :user AND clinic_id = :clinic AND client_id IN (SELECT l.client_id FROM ' . self::LINKS
            . " l WHERE l.user_id = :user AND l.clinic_id = :clinic AND l.status = '" . ClientLinkStatus::CONFIRMED->value . "')";

        return $this->page($scope, ['user' => SqlValues::binary($userId), 'clinic' => SqlValues::binary($clinicId)], [], null, $after, $limit);
    }

    public function clinicPage(string $clinicId, array $statuses, ?string $kind, ?RequestPosition $after, int $limit): array
    {
        return $this->page(
            'clinic_id = :clinic AND status IN (:statuses)',
            ['clinic'   => SqlValues::binary($clinicId), 'statuses' => $statuses],
            ['statuses' => ArrayParameterType::STRING],
            $kind,
            $after,
            $limit,
        );
    }

    /**
     * @param array<string, string|list<string>> $params
     * @param array<string, ArrayParameterType>  $types
     *
     * @return list<PortalRequestRecord>
     */
    private function page(string $where, array $params, array $types, ?string $kind, ?RequestPosition $after, int $limit): array
    {
        if (null !== $after) {
            $where .= self::AFTER;
            $params['at']    = SqlValues::utc($after->submittedAt);
            $params['after'] = SqlValues::binary($after->id);
        }

        $branches = [];

        if (null === $kind || PortalRequestKind::PET_CHANGE->value === $kind) {
            $branches[] = "SELECT '" . PortalRequestKind::PET_CHANGE->value . "' AS kind, " . self::COMMON
                . ', field, proposed_value, NULL AS prescription_id, NULL AS pet_condition, NULL AS resulting_prescription_id'
                . ' FROM ' . self::CHANGES . ' WHERE ' . $where;
        }

        if (null === $kind || PortalRequestKind::PRESCRIPTION_RENEWAL->value === $kind) {
            $branches[] = "SELECT '" . PortalRequestKind::PRESCRIPTION_RENEWAL->value . "' AS kind, " . self::COMMON
                . ', NULL AS field, NULL AS proposed_value, prescription_id, pet_condition, resulting_prescription_id'
                . ' FROM ' . self::RENEWALS . ' WHERE ' . $where;
        }

        if ([] === $branches) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            implode(' UNION ALL ', $branches) . ' ORDER BY submitted_at DESC, id DESC LIMIT :limit',
            $params + ['limit' => $limit],
            $types + ['limit' => ParameterType::INTEGER],
        );

        return array_map(self::record(...), $rows);
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function record(array $row): PortalRequestRecord
    {
        return new PortalRequestRecord(
            id: RowAccessor::uuid($row, 'id'),
            kind: RowAccessor::string($row, 'kind'),
            clinicId: RowAccessor::uuid($row, 'clinic_id'),
            clientId: RowAccessor::uuid($row, 'client_id'),
            userId: RowAccessor::uuid($row, 'user_id'),
            petId: RowAccessor::uuid($row, 'pet_id'),
            field: RowAccessor::nullableString($row, 'field'),
            proposedValue: RowAccessor::nullableString($row, 'proposed_value'),
            prescriptionId: RowAccessor::nullableUuid($row, 'prescription_id'),
            petCondition: RowAccessor::nullableString($row, 'pet_condition'),
            note: RowAccessor::nullableString($row, 'note'),
            status: RowAccessor::string($row, 'status'),
            submittedAt: SqlValues::date(RowAccessor::string($row, 'submitted_at')),
            resolvedAt: SqlValues::nullableDate(RowAccessor::nullableString($row, 'resolved_at')),
            resolvedByName: RowAccessor::nullableString($row, 'resolved_by_name'),
            resolutionMessage: RowAccessor::nullableString($row, 'resolution_message'),
            resultingPrescriptionId: RowAccessor::nullableUuid($row, 'resulting_prescription_id'),
            clientUnreachable: RequestRows::flag($row, 'client_unreachable'),
            closedReason: RowAccessor::nullableString($row, 'closed_reason'),
        );
    }
}
