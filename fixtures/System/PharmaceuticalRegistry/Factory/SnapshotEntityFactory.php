<?php

declare(strict_types=1);

namespace App\Fixtures\System\PharmaceuticalRegistry\Factory;

use App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Entity\SnapshotEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<SnapshotEntity>
 */
final class SnapshotEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return SnapshotEntity::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'id'           => Uuid::v7(),
            'source'       => 'ANMV',
            'status'       => 'APPLIED',
            'downloadedAt' => new \DateTimeImmutable(),
            'appliedAt'    => new \DateTimeImmutable(),
            'errorMessage' => null,
        ];
    }
}
