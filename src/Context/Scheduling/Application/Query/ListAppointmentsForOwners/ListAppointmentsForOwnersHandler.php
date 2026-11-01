<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\ListAppointmentsForOwners;

use App\Context\Scheduling\Domain\Booking\OnlineBookingSettingsRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Shared\Application\Pagination\Cursor;
use App\Shared\Application\Pagination\CursorPage;
use App\Shared\Application\Pagination\InvalidCursorException;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * Appointments of the clients `OR` of the animals, joined to Scheduling's
 * own booking motifs only. The cursor carries the scope it was issued for,
 * the start (UTC) and the id of the last row.
 */
#[AsMessageHandler]
final readonly class ListAppointmentsForOwnersHandler
{
    private const string FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private Connection $connection,
        private OnlineBookingSettingsRepositoryInterface $settings,
    ) {
    }

    /**
     * @return CursorPage<PortalAppointmentItem>
     */
    public function __invoke(ListAppointmentsForOwners $query): CursorPage
    {
        $limit = Cursor::clampLimit($query->limit);
        $after = null === $query->cursor ? null : self::position($query->cursor, $query->scope);

        if ([] === $query->ownerIds && [] === $query->animalIds) {
            return new CursorPage([], null);
        }

        $upcoming  = AppointmentListScope::UPCOMING === $query->scope;
        $direction = $upcoming ? 'ASC' : 'DESC';
        $holders   = [];
        $params    = [
            'clinicId'  => $query->clinicId,
            'occupying' => AppointmentStatus::OCCUPYING_SQL_VALUES,
            'now'       => $query->nowUtc->setTimezone(new \DateTimeZone('UTC'))->format(self::FORMAT),
            'limit'     => $limit + 1,
        ];
        $types = ['occupying' => ArrayParameterType::STRING, 'limit' => ParameterType::INTEGER];

        foreach (['owner_id' => $query->ownerIds, 'animal_id' => $query->animalIds] as $column => $ids) {
            if ([] !== $ids) {
                $holders[]       = 'a.' . $column . ' IN (:' . $column . ')';
                $params[$column] = array_map(static fn (string $id): string => Uuid::fromString($id)->toBinary(), $ids);
                $types[$column]  = ArrayParameterType::BINARY;
            }
        }

        $keyset = '';
        if (null !== $after) {
            $operator          = $upcoming ? '>' : '<';
            $keyset            = ' AND (a.starts_at_utc ' . $operator . ' :afterAt OR (a.starts_at_utc = :afterAt AND a.id ' . $operator . ' :afterId))';
            $params['afterAt'] = $after['at'];
            $params['afterId'] = Uuid::fromString($after['id'])->toBinary();
            $types['afterId']  = ParameterType::BINARY;
        }

        $sql = 'SELECT ' . PortalAppointmentItem::COLUMNS
            . ' FROM scheduling__appointments a ' . PortalAppointmentItem::MOTIF_JOIN
            . ' WHERE a.clinic_id = UUID_TO_BIN(:clinicId)'
            . ' AND (' . implode(' OR ', $holders) . ')'
            . ' AND ' . ($upcoming ? '' : 'NOT ') . '(a.status IN (:occupying) AND a.starts_at_utc >= :now)'
            . $keyset
            . ' ORDER BY a.starts_at_utc ' . $direction . ', a.id ' . $direction
            . ' LIMIT :limit';

        $settings = $this->settings->forClinic(ClinicId::fromString($query->clinicId));
        $items    = array_map(
            static fn (array $row): PortalAppointmentItem => PortalAppointmentItem::fromRow($row, $settings, $query->nowUtc),
            $this->connection->fetchAllAssociative($sql, $params, $types),
        );

        $next = null;
        if (\count($items) > $limit) {
            $items = \array_slice($items, 0, $limit);
            $last  = $items[$limit - 1];
            $next  = Cursor::fromPosition([
                's'  => $query->scope->value,
                'at' => $last->startsAtUtc->format(self::FORMAT),
                'id' => $last->id,
            ])->encode();
        }

        return new CursorPage($items, $next);
    }

    /**
     * @throws InvalidCursorException
     *
     * @return array{at: string, id: string}
     */
    private static function position(string $cursor, AppointmentListScope $scope): array
    {
        $position = Cursor::decode($cursor)->position();
        $at       = $position['at'] ?? null;
        $id       = $position['id'] ?? null;

        if ($scope->value !== ($position['s'] ?? null) || !\is_string($at) || !\is_string($id) || !Uuid::isValid($id)) {
            throw new InvalidCursorException();
        }

        $startsAt = \DateTimeImmutable::createFromFormat('!' . self::FORMAT, $at, new \DateTimeZone('UTC'));

        if (false === $startsAt || $startsAt->format(self::FORMAT) !== $at) {
            throw new InvalidCursorException();
        }

        return ['at' => $at, 'id' => $id];
    }
}
