<?php

declare(strict_types=1);

namespace App\System\AccessControl\Infrastructure\Persistence\Doctrine;

use App\System\AccessControl\Application\Port\AccessControlServiceInterface;
use App\System\AccessControl\Domain\ValueObject\Permission;
use App\System\AccessControl\Domain\ValueObject\SubjectId;
use App\System\AccessControl\Domain\ValueObject\TenantId;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DbalAccessControlService implements AccessControlServiceInterface
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function isGranted(SubjectId $subject, TenantId $tenant, Permission $permission): bool
    {
        $sql = <<<'SQL'
            SELECT 1
            FROM access_control__role_assignments ra
            JOIN access_control__role_permissions rp ON ra.role_key = rp.role_key
            WHERE ra.subject_id = :subject
              AND ra.tenant_id = :tenant
              AND rp.permission = :permission
            LIMIT 1
        SQL;

        $result = $this->connection->fetchOne($sql, [
            'subject'    => Uuid::fromString($subject->toString())->toBinary(),
            'tenant'     => Uuid::fromString($tenant->toString())->toBinary(),
            'permission' => $permission->toString(),
        ]);

        return false !== $result;
    }
}
