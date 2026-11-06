<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Repository;

use App\Shared\Infrastructure\Persistence\RowAccessor;
use App\System\IdentityAccess\Application\Port\BackofficeProfileStoreInterface;
use App\System\IdentityAccess\Application\Query\GetBackofficeProfile\BackofficeProfileView;
use App\System\IdentityAccess\Domain\ValueObject\ConsoleRole;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * The account (identity_access__users) left-joined to its settings: an
 * account without a row reads the defaults.
 */
final readonly class DbalBackofficeProfileStore implements BackofficeProfileStoreInterface
{
    private const string TABLE = 'identity_access__backoffice_profiles';

    private const string SELECT = 'SELECT u.id, u.email, u.created_at, u.status, p.display_name, p.time_zone, p.notify_registry_failure, p.notify_invitation_expiry, p.role'
        . ' FROM identity_access__users u LEFT JOIN ' . self::TABLE . ' p ON p.user_id = u.id'
        . " WHERE u.type = 'BACKOFFICE'";

    public function __construct(private Connection $connection)
    {
    }

    public function save(
        string $userId,
        string $displayName,
        string $timeZone,
        bool $notifyOnRegistryFailure,
        bool $notifyOnInvitationExpiry,
        \DateTimeImmutable $at,
    ): void {
        $this->connection->executeStatement(
            'INSERT INTO ' . self::TABLE . ' (user_id, display_name, time_zone, notify_registry_failure, notify_invitation_expiry, updated_at)'
            . ' VALUES (:user, :name, :zone, :registry, :invitation, :at)'
            . ' AS new ON DUPLICATE KEY UPDATE display_name = new.display_name, time_zone = new.time_zone,'
            . ' notify_registry_failure = new.notify_registry_failure, notify_invitation_expiry = new.notify_invitation_expiry, updated_at = new.updated_at',
            [
                'user'       => Uuid::fromString($userId)->toBinary(),
                'name'       => $displayName,
                'zone'       => $timeZone,
                'registry'   => (int) $notifyOnRegistryFailure,
                'invitation' => (int) $notifyOnInvitationExpiry,
                'at'         => $at->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            ],
        );
    }

    public function all(): array
    {
        $views = array_map(
            $this->view(...),
            $this->connection->fetchAllAssociative(self::SELECT . " AND u.status = 'ACTIVE'"),
        );
        usort($views, static fn (BackofficeProfileView $a, BackofficeProfileView $b): int => strcasecmp($a->displayName, $b->displayName));

        return $views;
    }

    public function find(string $userId): ?BackofficeProfileView
    {
        $row = $this->connection->fetchAssociative(self::SELECT . ' AND u.id = :user', ['user' => Uuid::fromString($userId)->toBinary()]);

        return false === $row ? null : $this->view($row);
    }

    public function saveRole(string $userId, ConsoleRole $role, \DateTimeImmutable $at): void
    {
        $view = $this->find($userId) ?? throw new \RuntimeException(\sprintf('No back-office account "%s".', $userId));

        // An account without a row gets one with its defaults first.
        $this->save($userId, $view->displayName, $view->timeZone, $view->notifyOnRegistryFailure, $view->notifyOnInvitationExpiry, $at);
        $this->connection->executeStatement(
            'UPDATE ' . self::TABLE . ' SET role = :role, updated_at = :at WHERE user_id = :user',
            ['role' => $role->value, 'at' => $at->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'), 'user' => Uuid::fromString($userId)->toBinary()],
        );
    }

    public function team(): array
    {
        $views = array_map($this->view(...), $this->connection->fetchAllAssociative(self::SELECT));
        $order = ['ACTIVE' => 0, 'PENDING' => 1, 'DISABLED' => 2];
        usort($views, static fn (BackofficeProfileView $a, BackofficeProfileView $b): int => ($order[$a->status] ?? 3) <=> ($order[$b->status] ?? 3) ?: strcasecmp($a->displayName, $b->displayName));

        return $views;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function view(array $row): BackofficeProfileView
    {
        $email = RowAccessor::string($row, 'email');
        $name  = RowAccessor::nullableString($row, 'display_name');

        return new BackofficeProfileView(
            userId: RowAccessor::uuid($row, 'id'),
            email: $email,
            displayName: $name ?? BackofficeProfileView::nameFromEmail($email),
            timeZone: RowAccessor::nullableString($row, 'time_zone') ?? BackofficeProfileView::DEFAULT_TIME_ZONE,
            notifyOnRegistryFailure: 0 !== (RowAccessor::nullableInt($row, 'notify_registry_failure') ?? 1),
            notifyOnInvitationExpiry: 0 !== (RowAccessor::nullableInt($row, 'notify_invitation_expiry') ?? 1),
            memberSince: new \DateTimeImmutable(RowAccessor::string($row, 'created_at'), new \DateTimeZone('UTC')),
            role: (ConsoleRole::tryFrom((string) RowAccessor::nullableString($row, 'role')) ?? ConsoleRole::ADMIN)->value,
            status: RowAccessor::string($row, 'status'),
        );
    }
}
