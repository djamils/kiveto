<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\FeatureCatalog\Infrastructure\Persistence\Doctrine\Entity;

use App\System\FeatureCatalog\Infrastructure\Persistence\Doctrine\Entity\ClinicFeatureEntity;
use App\System\FeatureCatalog\Infrastructure\Persistence\Doctrine\Entity\FeatureSettingsEntity;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class SchemaOnlyEntitiesTest extends TestCase
{
    public function testFeatureSettingsEntity(): void
    {
        $at     = new \DateTimeImmutable('2026-11-06 09:00:00');
        $entity = new FeatureSettingsEntity('portal.online_booking', 'available', true, $at);

        self::assertSame(['portal.online_booking', 'available', true, $at], [$entity->code, $entity->status, $entity->defaultOn, $entity->updatedAt]);
    }

    public function testClinicFeatureEntity(): void
    {
        $clinic = Uuid::v7();
        $actor  = Uuid::v7();
        $at     = new \DateTimeImmutable('2026-11-06 09:00:00');
        $entity = new ClinicFeatureEntity($clinic, 'notification.sms_reminders', false, $at, $actor, 'Djamil');

        self::assertSame([$clinic, 'notification.sms_reminders', false, $at, $actor, 'Djamil'], [
            $entity->clinicId, $entity->code, $entity->enabled, $entity->changedAt, $entity->changedById, $entity->changedByName,
        ]);
    }
}
