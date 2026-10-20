<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\ElectronicInvoicing\Infrastructure\Persistence;

use App\System\ElectronicInvoicing\Infrastructure\Persistence\Doctrine\Entity\LifecycleEventEntity;
use App\System\ElectronicInvoicing\Infrastructure\Persistence\Doctrine\Entity\UnprocessedFlowEntity;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class EntitiesTest extends TestCase
{
    public function testTheUnprocessedFlowRowHoldsItsColumns(): void
    {
        $entity = new UnprocessedFlowEntity();
        $id     = Uuid::fromString('01960000-0000-7000-8000-0000000ef001');
        $at     = new \DateTimeImmutable('2026-10-21 10:00');

        $entity->setId($id);
        $entity->setOrganisationId('812456903');
        $entity->setFlowId('f-1');
        $entity->setReason('unparsable');
        $entity->setError('malformed XML.');
        $entity->setContent('<broken');
        $entity->setAttempts(2);
        $entity->setLastSeenAt($at);

        self::assertSame($id, $entity->getId());
        self::assertSame('812456903', $entity->getOrganisationId());
        self::assertSame('f-1', $entity->getFlowId());
        self::assertSame('unparsable', $entity->getReason());
        self::assertSame('malformed XML.', $entity->getError());
        self::assertSame('<broken', $entity->getContent());
        self::assertSame(2, $entity->getAttempts());
        self::assertSame($at, $entity->getLastSeenAt());
    }

    public function testTheLifecycleEventRowHoldsItsIds(): void
    {
        $entity = new LifecycleEventEntity();
        $id     = Uuid::fromString('01960000-0000-7000-8000-0000000ef002');
        $parent = Uuid::fromString('01960000-0000-7000-8000-0000000ef003');

        $entity->setId($id);
        $entity->setTransmissionId($parent);

        self::assertSame($id, $entity->getId());
        self::assertSame($parent, $entity->getTransmissionId());
    }
}
