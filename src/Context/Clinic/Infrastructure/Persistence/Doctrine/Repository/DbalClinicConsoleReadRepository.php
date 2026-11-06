<?php

declare(strict_types=1);

namespace App\Context\Clinic\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Clinic\Application\Port\ClinicConsoleReadRepositoryInterface;
use App\Context\Clinic\Application\Query\Console\ConsoleClinicRow;
use App\Context\Clinic\Application\Query\Console\ConsoleMemberRow;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DbalClinicConsoleReadRepository implements ClinicConsoleReadRepositoryInterface
{
    private const string CLINICS = 'SELECT c.id, c.name, c.slug, c.letterhead_city, c.letterhead_postal_code, c.letterhead_siret, c.letterhead_legal_name,'
        . ' c.status, c.created_at, a.plan, a.pilot_ends_on, a.onboarded_at, a.suspended_at,'
        . " (SELECT COUNT(*) FROM clinic__clinic_memberships m WHERE m.clinic_id = c.id AND m.status = 'ACTIVE') AS members,"
        . " (SELECT COUNT(*) FROM clinic__clinic_memberships m WHERE m.clinic_id = c.id AND m.status = 'ACTIVE' AND m.role = 'VETERINARY') AS veterinarians"
        . ' FROM clinic__clinics c LEFT JOIN ' . DbalClinicAccountRepository::TABLE . ' a ON a.clinic_id = c.id'
        . " WHERE c.status <> 'closed'";

    public function __construct(private Connection $connection)
    {
    }

    public function clinics(): array
    {
        return array_map($this->clinicRow(...), $this->connection->fetchAllAssociative(self::CLINICS . ' ORDER BY c.name, c.id'));
    }

    public function clinic(string $clinicId): ?ConsoleClinicRow
    {
        $row = $this->connection->fetchAssociative(self::CLINICS . ' AND c.id = :clinic', ['clinic' => Uuid::fromString($clinicId)->toBinary()]);

        return false === $row ? null : $this->clinicRow($row);
    }

    public function searchClinics(string $text, int $limit): array
    {
        $like = '%' . addcslashes(trim($text), '%_\\') . '%';

        return array_map($this->clinicRow(...), $this->connection->fetchAllAssociative(
            self::CLINICS . ' AND (c.name LIKE :text OR c.slug LIKE :text OR c.letterhead_siret LIKE :compact OR c.letterhead_legal_name LIKE :text)'
            . ' ORDER BY c.name, c.id LIMIT ' . max(1, $limit),
            ['text' => $like, 'compact' => '%' . addcslashes((string) preg_replace('/\s+/', '', $text), '%_\\') . '%'],
        ));
    }

    public function members(?string $clinicId = null, ?string $userId = null): array
    {
        $conditions = [];
        $params     = [];

        if (null !== $clinicId) {
            $conditions[]     = 'm.clinic_id = :clinic';
            $params['clinic'] = Uuid::fromString($clinicId)->toBinary();
        }

        if (null !== $userId) {
            $conditions[]   = 'm.user_id = :user';
            $params['user'] = Uuid::fromString($userId)->toBinary();
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT m.id, m.clinic_id, c.name AS clinic_name, m.user_id, m.role, m.is_clinic_administrator, m.status, m.created_at_utc,'
            . ' p.first_name, p.last_name, p.display_name, p.professional_title, p.phone_number, p.registration_number'
            . ' FROM clinic__clinic_memberships m'
            . ' JOIN clinic__clinics c ON c.id = m.clinic_id'
            . ' LEFT JOIN clinic__staff_profiles p ON p.membership_id = m.id'
            . ([] !== $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '')
            . ' ORDER BY c.name, m.is_clinic_administrator DESC, p.last_name, p.first_name, m.id',
            $params,
        );

        return array_map(static fn (array $row): ConsoleMemberRow => new ConsoleMemberRow(
            membershipId: RowAccessor::uuid($row, 'id'),
            clinicId: RowAccessor::uuid($row, 'clinic_id'),
            clinicName: RowAccessor::string($row, 'clinic_name'),
            userId: RowAccessor::uuid($row, 'user_id'),
            role: RowAccessor::string($row, 'role'),
            isAdministrator: 1 === RowAccessor::int($row, 'is_clinic_administrator'),
            status: RowAccessor::string($row, 'status'),
            since: new \DateTimeImmutable(RowAccessor::string($row, 'created_at_utc'), new \DateTimeZone('UTC')),
            firstName: RowAccessor::nullableString($row, 'first_name') ?? '',
            lastName: RowAccessor::nullableString($row, 'last_name') ?? '',
            displayName: RowAccessor::nullableString($row, 'display_name') ?? '',
            professionalTitle: RowAccessor::nullableString($row, 'professional_title'),
            phone: RowAccessor::nullableString($row, 'phone_number'),
            registrationNumber: RowAccessor::nullableString($row, 'registration_number'),
        ), $rows);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function clinicRow(array $row): ConsoleClinicRow
    {
        $pilot = RowAccessor::nullableString($row, 'pilot_ends_on');

        return new ConsoleClinicRow(
            id: RowAccessor::uuid($row, 'id'),
            name: RowAccessor::string($row, 'name'),
            slug: RowAccessor::string($row, 'slug'),
            city: self::blank(RowAccessor::nullableString($row, 'letterhead_city')),
            postalCode: self::blank(RowAccessor::nullableString($row, 'letterhead_postal_code')),
            siret: self::blank(RowAccessor::nullableString($row, 'letterhead_siret')),
            legalName: self::blank(RowAccessor::nullableString($row, 'letterhead_legal_name')),
            status: RowAccessor::string($row, 'status'),
            plan: RowAccessor::nullableString($row, 'plan') ?? 'pilot',
            pilotEndsOn: null !== $pilot ? new \DateTimeImmutable($pilot . ' 00:00:00', new \DateTimeZone('UTC')) : null,
            onboardedAt: self::date(RowAccessor::nullableString($row, 'onboarded_at')),
            suspendedAt: self::date(RowAccessor::nullableString($row, 'suspended_at')),
            createdAt: new \DateTimeImmutable(RowAccessor::string($row, 'created_at'), new \DateTimeZone('UTC')),
            veterinarians: RowAccessor::int($row, 'veterinarians'),
            members: RowAccessor::int($row, 'members'),
        );
    }

    private static function blank(?string $value): ?string
    {
        return null === $value || '' === trim($value) ? null : $value;
    }

    private static function date(?string $value): ?\DateTimeImmutable
    {
        return null !== $value ? new \DateTimeImmutable($value, new \DateTimeZone('UTC')) : null;
    }
}
