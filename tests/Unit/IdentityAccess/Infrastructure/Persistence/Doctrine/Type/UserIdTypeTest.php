<?php

declare(strict_types=1);

namespace App\Tests\Unit\IdentityAccess\Infrastructure\Persistence\Doctrine\Type;

use App\IdentityAccess\Domain\ValueObject\UserId;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Type\UserIdType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use PHPUnit\Framework\TestCase;

final class UserIdTypeTest extends TestCase
{
    private UserIdType $type;

    protected function setUp(): void
    {
        $this->type = new UserIdType();
    }

    public function testNameAndSqlDeclaration(): void
    {
        $platform = $this->createMock(AbstractPlatform::class);
        $platform->expects(self::once())
            ->method('getGuidTypeDeclarationSQL')
            ->with([])
            ->willReturn('GUID')
        ;

        self::assertSame(UserIdType::NAME, $this->type->getName());
        self::assertSame('GUID', $this->type->getSQLDeclaration([], $platform));
        self::assertTrue($this->type->requiresSQLCommentHint($platform));
    }

    public function testConvertToDatabaseValue(): void
    {
        $platform = $this->createStub(AbstractPlatform::class);
        $id       = UserId::fromString('11111111-1111-1111-1111-111111111111');

        self::assertNull($this->type->convertToDatabaseValue(null, $platform));
        self::assertSame($id->toString(), $this->type->convertToDatabaseValue($id, $platform));
        self::assertSame($id->toString(), $this->type->convertToDatabaseValue($id->toString(), $platform));

        $this->expectException(\InvalidArgumentException::class);
        $this->type->convertToDatabaseValue(123, $platform);
    }

    public function testConvertToPhpValue(): void
    {
        $platform = $this->createStub(AbstractPlatform::class);
        $id       = UserId::fromString('11111111-1111-1111-1111-111111111111');

        self::assertNull($this->type->convertToPHPValue(null, $platform));
        self::assertSame($id, $this->type->convertToPHPValue($id, $platform));

        $converted = $this->type->convertToPHPValue($id->toString(), $platform);
        self::assertInstanceOf(UserId::class, $converted);
        self::assertSame($id->toString(), $converted->toString());

        $this->expectException(\InvalidArgumentException::class);
        $this->type->convertToPHPValue(123, $platform);
    }
}
