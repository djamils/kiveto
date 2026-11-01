<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\ClientPortal\Domain\Request\ClosureReason;
use App\Context\ClientPortal\Domain\Request\RequestResolution;
use App\Context\ClientPortal\Domain\Request\RequestStatus;
use App\Context\ClientPortal\Domain\Request\RequestSubject;
use App\Shared\Infrastructure\Persistence\RowAccessor;

/**
 * The columns the three request tables share: resolution, closure and the
 * subject a closure looks them up by.
 */
final class RequestRows
{
    /**
     * The `WHERE` of the submitted rows hanging on a subject, or null when
     * the table does not carry that subject.
     *
     * @param array<value-of<RequestSubject>, string> $columns subject → column of the table
     *
     * @return array{0: string, 1: array<string, string>}|null
     */
    public static function subjectFilter(array $columns, RequestSubject $subject, string $subjectId, ?string $clinicId): ?array
    {
        $column = $columns[$subject->value] ?? null;

        if (null === $column) {
            return null;
        }

        $where  = $column . ' = :subject AND status = :submitted';
        $params = ['subject' => SqlValues::binary($subjectId), 'submitted' => RequestStatus::SUBMITTED->value];

        if (RequestSubject::USER !== $subject && null !== $clinicId) {
            $where .= ' AND clinic_id = :clinic';
            $params['clinic'] = SqlValues::binary($clinicId);
        }

        return [$where, $params];
    }

    /**
     * @return array{resolved_at: ?string, resolved_by_user_id: ?string, resolved_by_name: ?string, resolution_message: ?string}
     */
    public static function resolution(?RequestResolution $resolution): array
    {
        return [
            'resolved_at'         => SqlValues::nullableUtc($resolution?->resolvedAt()),
            'resolved_by_user_id' => SqlValues::nullableBinary($resolution?->resolvedByUserId()),
            'resolved_by_name'    => $resolution?->resolvedByName(),
            'resolution_message'  => $resolution?->message(),
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function hydrateResolution(array $row, RequestStatus $status): ?RequestResolution
    {
        $resolvedAt = RowAccessor::nullableString($row, 'resolved_at');

        if (null === $resolvedAt || (RequestStatus::DONE !== $status && RequestStatus::REJECTED !== $status)) {
            return null;
        }

        return RequestResolution::reconstitute(
            $status,
            SqlValues::date($resolvedAt),
            RowAccessor::uuid($row, 'resolved_by_user_id'),
            RowAccessor::string($row, 'resolved_by_name'),
            RowAccessor::nullableString($row, 'resolution_message'),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function closedReason(array $row): ?ClosureReason
    {
        $reason = RowAccessor::nullableString($row, 'closed_reason');

        return null === $reason ? null : ClosureReason::from($reason);
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function flag(array $row, string $key): bool
    {
        return 1 === RowAccessor::int($row, $key);
    }

    /**
     * @return array<string, mixed>
     */
    public static function json(string $value): array
    {
        $decoded = json_decode($value, true, 8, \JSON_THROW_ON_ERROR);
        \assert(\is_array($decoded));
        $object = [];

        foreach ($decoded as $key => $item) {
            $object[(string) $key] = $item;
        }

        return $object;
    }
}
