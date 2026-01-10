<?php

declare(strict_types=1);

namespace App\Tests\Unit\Translation\Domain\Event;

use App\Translation\Domain\Event\TranslationDeleted;
use PHPUnit\Framework\TestCase;

final class TranslationDeletedTest extends TestCase
{
    public function testPayloadAndAggregateId(): void
    {
        $removedAt = new \DateTimeImmutable('2024-01-01T12:00:00Z');
        $event     = new TranslationDeleted('portal', 'en-GB', 'common', 'bar', 'actor-2', $removedAt);

        self::assertSame('portal:en-GB:common', $event->aggregateId());
        self::assertSame(
            [
                'scope'     => 'portal',
                'locale'    => 'en-GB',
                'domain'    => 'common',
                'key'       => 'bar',
                'actorId'   => 'actor-2',
                'removedAt' => $removedAt->format(\DATE_ATOM),
            ],
            $event->payload(),
        );
        self::assertSame('translation.translation.deleted.v1', $event->type());
    }
}
