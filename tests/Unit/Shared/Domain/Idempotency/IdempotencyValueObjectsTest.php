<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\Idempotency;

use App\Shared\Domain\Idempotency\IdempotencyKey;
use App\Shared\Domain\Idempotency\IdempotencyKeyReusedException;
use App\Shared\Domain\Idempotency\InvalidIdempotencyKeyException;
use App\Shared\Domain\Idempotency\RequestFingerprint;
use App\Shared\Domain\Idempotency\StoredResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IdempotencyValueObjectsTest extends TestCase
{
    public function testAKeyKeepsItsValue(): void
    {
        $key = IdempotencyKey::fromString('9f6c1b1e-6d1f-4c43-9a59-6a1d2c3b4a5f');

        self::assertSame('9f6c1b1e-6d1f-4c43-9a59-6a1d2c3b4a5f', $key->toString());
        self::assertTrue($key->equals(IdempotencyKey::fromString('9f6c1b1e-6d1f-4c43-9a59-6a1d2c3b4a5f')));
        self::assertFalse($key->equals(IdempotencyKey::fromString('other')));
        self::assertSame(128, \strlen(IdempotencyKey::fromString(str_repeat('k', 128))->toString()));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideAnInvalidKeyIsRefusedCases(): iterable
    {
        yield 'empty' => [''];
        yield 'too long' => [str_repeat('k', 129)];
        yield 'space' => ['a key'];
        yield 'non ascii' => ['clé'];
        yield 'control character' => ["key\n"];
    }

    #[DataProvider('provideAnInvalidKeyIsRefusedCases')]
    public function testAnInvalidKeyIsRefused(string $value): void
    {
        $this->expectException(InvalidIdempotencyKeyException::class);
        $this->expectExceptionMessage('An idempotency key must be 1 to 128 visible ASCII characters.');

        IdempotencyKey::fromString($value);
    }

    public function testAFingerprintIsTheSha256OfTheCanonicalRequest(): void
    {
        $fingerprint = RequestFingerprint::of('POST /x {}');

        self::assertSame(hash('sha256', 'POST /x {}'), $fingerprint->toString());
        self::assertTrue($fingerprint->equals(RequestFingerprint::fromHash(hash('sha256', 'POST /x {}'))));
        self::assertFalse($fingerprint->equals(RequestFingerprint::of('POST /x {"a":1}')));
    }

    public function testAMalformedFingerprintHashIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        RequestFingerprint::fromHash(strtoupper(hash('sha256', 'x')));
    }

    public function testAStoredResponseKeepsItsStatusAndBody(): void
    {
        $response = StoredResponse::create(201, '{"id":1}');

        self::assertSame(201, $response->status());
        self::assertSame('{"id":1}', $response->body());
        self::assertNull(StoredResponse::create(204, '')->body());
        self::assertNull(StoredResponse::create(204, null)->body());
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function provideAnInvalidStatusIsRefusedCases(): iterable
    {
        yield 'too low' => [99];
        yield 'too high' => [600];
    }

    #[DataProvider('provideAnInvalidStatusIsRefusedCases')]
    public function testAnInvalidStatusIsRefused(int $status): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('Invalid HTTP status %d.', $status));

        StoredResponse::create($status, null);
    }

    public function testTheReusedKeyExceptionNamesTheKey(): void
    {
        $exception = new IdempotencyKeyReusedException(IdempotencyKey::fromString('abc'));

        self::assertSame('The idempotency key "abc" was already used for a different request.', $exception->getMessage());
    }
}
