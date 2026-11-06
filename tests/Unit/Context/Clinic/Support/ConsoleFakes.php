<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Support;

use App\Context\Clinic\Application\Port\ClinicAppointmentsInterface;
use App\Context\Clinic\Application\Port\ClinicConsoleReadRepositoryInterface;
use App\Context\Clinic\Application\Port\InvitationStatusReaderInterface;
use App\Context\Clinic\Application\Query\Console\ConsoleClinicRow;
use App\Context\Clinic\Application\Query\Console\ConsoleMemberRow;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;

/**
 * Hand-written doubles of the console: its read side, the invitation
 * statuses of IdentityAccess, the appointments of Scheduling, and a
 * recording event bus.
 */
final class ConsoleFakes implements ClinicConsoleReadRepositoryInterface, InvitationStatusReaderInterface, ClinicAppointmentsInterface, EventBusInterface
{
    /** @var list<ConsoleClinicRow> */
    public array $clinicRows = [];

    /** @var list<ConsoleMemberRow> */
    public array $memberRows = [];

    /** @var array<string, string> userId → invitation status */
    public array $statuses = [];

    /** @var list<array{string, list<string>}> clinic and user ids whose statuses were asked */
    public array $askedStatuses = [];

    /** @var list<string> clinics with at least one appointment */
    public array $booked = [];

    /** @var list<object> */
    public array $events = [];

    public static function clinicRow(string $id, string $status = 'active', ?\DateTimeImmutable $onboardedAt = null): ConsoleClinicRow
    {
        $at = new \DateTimeImmutable('2026-09-01 08:00:00', new \DateTimeZone('UTC'));

        return new ConsoleClinicRow($id, 'Clinique ' . $id, 'clinique-' . $id, 'Lyon', '69003', null, null, $status, 'pilot', null, $onboardedAt, null, $at, 1, 2);
    }

    public static function memberRow(
        string $userId,
        string $clinicId,
        string $firstName = '',
        string $lastName = '',
        string $displayName = '',
        string $status = 'ACTIVE',
        ?string $professionalTitle = null,
    ): ConsoleMemberRow {
        return new ConsoleMemberRow(
            'm-' . $userId,
            $clinicId,
            'Clinique ' . $clinicId,
            $userId,
            'VETERINARY',
            false,
            $status,
            new \DateTimeImmutable('2026-09-01 08:00:00', new \DateTimeZone('UTC')),
            $firstName,
            $lastName,
            $displayName,
            $professionalTitle,
            null,
            null,
        );
    }

    public function clinics(): array
    {
        return $this->clinicRows;
    }

    public function clinic(string $clinicId): ?ConsoleClinicRow
    {
        foreach ($this->clinicRows as $row) {
            if ($row->id === $clinicId) {
                return $row;
            }
        }

        return null;
    }

    public function searchClinics(string $text, int $limit): array
    {
        return \array_slice(array_values(array_filter($this->clinicRows, static fn (ConsoleClinicRow $row): bool => str_contains($row->name, $text))), 0, $limit);
    }

    public function members(?string $clinicId = null, ?string $userId = null): array
    {
        return array_values(array_filter(
            $this->memberRows,
            static fn (ConsoleMemberRow $row): bool => (null === $clinicId || $row->clinicId === $clinicId) && (null === $userId || $row->userId === $userId),
        ));
    }

    public function statuses(string $clinicId, array $userIds): array
    {
        $this->askedStatuses[] = [$clinicId, $userIds];

        return array_intersect_key($this->statuses, array_flip($userIds));
    }

    public function hasAny(string $clinicId): bool
    {
        return \in_array($clinicId, $this->booked, true);
    }

    public function publish(array $stamps, object ...$events): void
    {
        array_push($this->events, ...$events);
    }

    public function publisher(): DomainEventPublisher
    {
        return new DomainEventPublisher($this);
    }
}
