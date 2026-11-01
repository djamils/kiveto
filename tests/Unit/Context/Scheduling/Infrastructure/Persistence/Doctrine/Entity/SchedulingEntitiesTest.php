<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Infrastructure\Persistence\Doctrine\Entity;

use App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Entity\AppointmentEntity;
use App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Entity\PlanningBlockEntity;
use PHPUnit\Framework\TestCase;

/**
 * The optimistic-lock version starts at 1 and the timestamps are kept as set.
 */
final class SchedulingEntitiesTest extends TestCase
{
    public function testAppointmentEntityStartsAtVersionOne(): void
    {
        self::assertSame(1, new AppointmentEntity()->getVersion());
    }

    public function testPlanningBlockEntityKeepsItsTimestampsAndStartsAtVersionOne(): void
    {
        $createdAt = new \DateTimeImmutable('2026-04-01 08:00:00');
        $updatedAt = new \DateTimeImmutable('2026-04-02 09:00:00');

        $entity = new PlanningBlockEntity();
        $entity->setCreatedAt($createdAt);
        $entity->setUpdatedAt($updatedAt);

        self::assertSame($createdAt, $entity->getCreatedAt());
        self::assertSame($updatedAt, $entity->getUpdatedAt());
        self::assertSame(1, $entity->getVersion());
    }
}
