<?php

declare(strict_types=1);

namespace Tests\Unit\System\PharmaceuticalRegistry\Domain\ValueObject;

use App\System\PharmaceuticalRegistry\Domain\ValueObject\ContentHash;
use PHPUnit\Framework\TestCase;

final class ContentHashTest extends TestCase
{
    public function testDeterminism(): void
    {
        $dto1 = ['b' => '2', 'a' => '1'];
        $dto2 = ['a' => '1', 'b' => '2'];

        self::assertSame(ContentHash::of($dto1)->toString(), ContentHash::of($dto2)->toString());
    }

    public function testDifferentDataProducesDifferentHash(): void
    {
        $dto1 = ['a' => '1'];
        $dto2 = ['a' => '2'];

        self::assertNotSame(ContentHash::of($dto1)->toString(), ContentHash::of($dto2)->toString());
    }

    public function testNestedDeterminism(): void
    {
        $dto1 = ['nested' => ['z' => '3', 'a' => '1']];
        $dto2 = ['nested' => ['a' => '1', 'z' => '3']];

        self::assertSame(ContentHash::of($dto1)->toString(), ContentHash::of($dto2)->toString());
    }

    public function testEquality(): void
    {
        $dto = ['key' => 'value'];

        self::assertTrue(ContentHash::of($dto)->equals(ContentHash::of($dto)));
    }

    public function testFromString(): void
    {
        $hex  = str_repeat('a', 64);
        $hash = ContentHash::fromString($hex);

        self::assertSame($hex, $hash->toString());
    }
}
