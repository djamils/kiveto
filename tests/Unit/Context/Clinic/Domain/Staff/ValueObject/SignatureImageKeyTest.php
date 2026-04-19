<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Domain\Staff\ValueObject;

use App\Context\Clinic\Domain\Staff\ValueObject\SignatureImageKey;
use PHPUnit\Framework\TestCase;

final class SignatureImageKeyTest extends TestCase
{
    public function testFromStringWithValidKey(): void
    {
        $key = SignatureImageKey::fromString('signatures/clinic-1/vet-abc123.png');

        self::assertSame('signatures/clinic-1/vet-abc123.png', $key->toString());
    }

    public function testFromStringRejectsEmptyValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Signature image key cannot be empty.');

        SignatureImageKey::fromString('');
    }

    public function testFromStringRejectsWhitespaceOnly(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Signature image key cannot be empty.');

        SignatureImageKey::fromString('   ');
    }

    public function testFromStringRejectsValueExceeding255Characters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Signature image key cannot exceed 255 characters');

        SignatureImageKey::fromString(str_repeat('a', 256));
    }

    public function testFromStringRejectsPathTraversal(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must not contain ".."');

        SignatureImageKey::fromString('foo/../bar');
    }

    public function testFromStringRejectsLeadingSlash(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must not start with a leading slash');

        SignatureImageKey::fromString('/leading/slash');
    }

    public function testFromStringRejectsInvalidCharacters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('contains invalid characters');

        SignatureImageKey::fromString('key with spaces');
    }

    public function testFromStringRejectsUnicode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('contains invalid characters');

        SignatureImageKey::fromString('clé-signature.png');
    }

    public function testEquals(): void
    {
        $a = SignatureImageKey::fromString('signatures/a.png');
        $b = SignatureImageKey::fromString('signatures/a.png');
        $c = SignatureImageKey::fromString('signatures/b.png');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }
}
