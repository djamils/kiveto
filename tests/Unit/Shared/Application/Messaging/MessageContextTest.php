<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Application\Messaging;

use App\Shared\Application\Messaging\MessageContext;
use App\Shared\Infrastructure\Bus\Messenger\Stamp\MessageMetadataStamp;
use PHPUnit\Framework\TestCase;

final class MessageContextTest extends TestCase
{
    public function testPushAndCurrentMetadata(): void
    {
        $context = new MessageContext();

        $metadata = new MessageMetadataStamp(
            messageId: 'msg-123',
            occurredAt: new \DateTimeImmutable('2025-01-05 10:00:00'),
            correlationId: 'corr-123',
            causationId: null,
            actorId: 'user-456',
        );

        $context->push($metadata);

        self::assertSame($metadata, $context->current());
        self::assertSame('msg-123', $context->messageId());
        self::assertSame('corr-123', $context->correlationId());
        self::assertSame('user-456', $context->actorId());
    }

    public function testPopRemovesLastMetadata(): void
    {
        $context = new MessageContext();

        $metadata1 = new MessageMetadataStamp(
            messageId: 'msg-1',
            occurredAt: new \DateTimeImmutable('2025-01-05 10:00:00'),
            correlationId: 'corr-1',
            causationId: null,
        );

        $metadata2 = new MessageMetadataStamp(
            messageId: 'msg-2',
            occurredAt: new \DateTimeImmutable('2025-01-05 10:00:01'),
            correlationId: 'corr-2',
            causationId: 'msg-1',
        );

        $context->push($metadata1);
        $context->push($metadata2);

        self::assertSame('msg-2', $context->messageId());

        $context->pop();

        self::assertSame('msg-1', $context->messageId());
    }

    public function testCurrentThrowsWhenEmpty(): void
    {
        $context = new MessageContext();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('No message metadata in context');

        $context->current();
    }

    public function testPopThrowsWhenEmpty(): void
    {
        $context = new MessageContext();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot pop from empty message context stack');

        $context->pop();
    }

    public function testNestedContexts(): void
    {
        $context = new MessageContext();

        $root = new MessageMetadataStamp(
            messageId: 'msg-root',
            occurredAt: new \DateTimeImmutable('2025-01-05 10:00:00'),
            correlationId: 'corr-root',
            causationId: null,
        );

        $nested1 = new MessageMetadataStamp(
            messageId: 'msg-nested-1',
            occurredAt: new \DateTimeImmutable('2025-01-05 10:00:01'),
            correlationId: 'corr-root',
            causationId: 'msg-root',
        );

        $nested2 = new MessageMetadataStamp(
            messageId: 'msg-nested-2',
            occurredAt: new \DateTimeImmutable('2025-01-05 10:00:02'),
            correlationId: 'corr-root',
            causationId: 'msg-nested-1',
        );

        $context->push($root);
        self::assertSame('msg-root', $context->messageId());
        self::assertNull($context->causationId());

        $context->push($nested1);
        self::assertSame('msg-nested-1', $context->messageId());
        self::assertSame('msg-root', $context->causationId());

        $context->push($nested2);
        self::assertSame('msg-nested-2', $context->messageId());
        self::assertSame('msg-nested-1', $context->causationId());

        $context->pop();
        self::assertSame('msg-nested-1', $context->messageId());

        $context->pop();
        self::assertSame('msg-root', $context->messageId());

        $context->pop();

        $this->expectException(\LogicException::class);
        $context->current();
    }
}
