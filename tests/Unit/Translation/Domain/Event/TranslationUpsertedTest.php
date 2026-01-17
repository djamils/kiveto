<?php

declare(strict_types=1);

namespace App\Tests\Unit\Translation\Domain\Event;

use App\Translation\Domain\Event\TranslationUpserted;
use PHPUnit\Framework\TestCase;

final class TranslationUpsertedTest extends TestCase
{
    public function testPayloadAndAggregateId(): void
    {
        $event = new TranslationUpserted('clinic', 'fr-FR', 'messages', 'foo', 'actor-1');

        self::assertSame('clinic:fr-FR:messages', $event->aggregateId());
        self::assertSame(
            [
                'scope'   => 'clinic',
                'locale'  => 'fr-FR',
                'domain'  => 'messages',
                'key'     => 'foo',
                'actorId' => 'actor-1',
            ],
            $event->payload(),
        );
        self::assertSame('translation.translation.upserted.v1', $event->name());
    }
}
