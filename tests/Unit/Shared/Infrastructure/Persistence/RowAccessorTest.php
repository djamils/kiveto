<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Persistence;

use App\Shared\Infrastructure\Persistence\RowAccessor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class RowAccessorTest extends TestCase
{
    // ───────────── string() ─────────────

    public function testStringReturnsStringValueAsIs(): void
    {
        self::assertSame('hello', RowAccessor::string(['name' => 'hello'], 'name'));
    }

    public function testStringCastsIntToString(): void
    {
        self::assertSame('42', RowAccessor::string(['count' => 42], 'count'));
    }

    public function testStringCastsFloatToString(): void
    {
        self::assertSame('3.14', RowAccessor::string(['ratio' => 3.14], 'ratio'));
    }

    public function testStringCastsBoolToString(): void
    {
        self::assertSame('1', RowAccessor::string(['active' => true], 'active'));
        self::assertSame('', RowAccessor::string(['active' => false], 'active'));
    }

    public function testStringThrowsWhenKeyMissing(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Missing key "name" in row.');

        RowAccessor::string([], 'name');
    }

    public function testStringThrowsWhenValueIsArray(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Row key "data" is not stringable.');

        RowAccessor::string(['data' => ['nested']], 'data');
    }

    public function testStringThrowsWhenValueIsObject(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Row key "data" is not stringable.');

        RowAccessor::string(['data' => new \stdClass()], 'data');
    }

    public function testStringTreatsNullAsMissing(): void
    {
        // Coalesce in the helper turns explicit null into the "missing key" branch.
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Missing key "name" in row.');

        RowAccessor::string(['name' => null], 'name');
    }

    // ───────────── nullableString() ─────────────

    public function testNullableStringReturnsNullForMissingKey(): void
    {
        self::assertNull(RowAccessor::nullableString([], 'name'));
    }

    public function testNullableStringReturnsNullForExplicitNull(): void
    {
        self::assertNull(RowAccessor::nullableString(['name' => null], 'name'));
    }

    public function testNullableStringReturnsStringValueAsIs(): void
    {
        self::assertSame('hello', RowAccessor::nullableString(['name' => 'hello'], 'name'));
    }

    public function testNullableStringCastsScalarValues(): void
    {
        self::assertSame('42', RowAccessor::nullableString(['count' => 42], 'count'));
        self::assertSame('3.14', RowAccessor::nullableString(['ratio' => 3.14], 'ratio'));
        self::assertSame('1', RowAccessor::nullableString(['active' => true], 'active'));
    }

    public function testNullableStringThrowsForNonScalarValue(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Row key "data" is not stringable.');

        RowAccessor::nullableString(['data' => new \stdClass()], 'data');
    }

    // ───────────── int() ─────────────

    public function testIntReturnsIntValueAsIs(): void
    {
        self::assertSame(42, RowAccessor::int(['count' => 42], 'count'));
    }

    public function testIntCastsNumericString(): void
    {
        self::assertSame(42, RowAccessor::int(['count' => '42'], 'count'));
    }

    public function testIntCastsNumericStringWithSign(): void
    {
        self::assertSame(-7, RowAccessor::int(['delta' => '-7'], 'delta'));
    }

    public function testIntCastsFloat(): void
    {
        self::assertSame(3, RowAccessor::int(['ratio' => 3.7], 'ratio'));
    }

    public function testIntCastsBool(): void
    {
        self::assertSame(1, RowAccessor::int(['active' => true], 'active'));
        self::assertSame(0, RowAccessor::int(['active' => false], 'active'));
    }

    public function testIntThrowsWhenKeyMissing(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Missing key "count" in row.');

        RowAccessor::int([], 'count');
    }

    public function testIntThrowsForNonNumericString(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Row key "count" is not int-castable.');

        RowAccessor::int(['count' => 'abc'], 'count');
    }

    public function testIntThrowsForArray(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Row key "count" is not int-castable.');

        RowAccessor::int(['count' => [1, 2]], 'count');
    }

    public function testIntTreatsNullAsMissing(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Missing key "count" in row.');

        RowAccessor::int(['count' => null], 'count');
    }

    // ───────────── nullableInt() ─────────────

    public function testNullableIntReturnsNullForMissingKey(): void
    {
        self::assertNull(RowAccessor::nullableInt([], 'count'));
    }

    public function testNullableIntReturnsNullForExplicitNull(): void
    {
        self::assertNull(RowAccessor::nullableInt(['count' => null], 'count'));
    }

    public function testNullableIntReturnsIntValueAsIs(): void
    {
        self::assertSame(42, RowAccessor::nullableInt(['count' => 42], 'count'));
    }

    public function testNullableIntCastsNumericString(): void
    {
        self::assertSame(42, RowAccessor::nullableInt(['count' => '42'], 'count'));
    }

    public function testNullableIntCastsFloat(): void
    {
        self::assertSame(3, RowAccessor::nullableInt(['ratio' => 3.7], 'ratio'));
    }

    public function testNullableIntCastsBool(): void
    {
        self::assertSame(1, RowAccessor::nullableInt(['active' => true], 'active'));
    }

    public function testNullableIntThrowsForNonNumericString(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Row key "count" is not int-castable.');

        RowAccessor::nullableInt(['count' => 'abc'], 'count');
    }

    public function testNullableIntThrowsForArray(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Row key "count" is not int-castable.');

        RowAccessor::nullableInt(['count' => [1, 2]], 'count');
    }

    // ───────────── uuid() ─────────────

    public function testUuidDecodesBinaryUuidToRfc4122(): void
    {
        $uuid    = Uuid::v7();
        $rfc4122 = $uuid->toRfc4122();
        $row     = ['id' => $uuid->toBinary()];

        self::assertSame($rfc4122, RowAccessor::uuid($row, 'id'));
    }

    public function testUuidThrowsWhenKeyMissing(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Missing key "id" in row.');

        RowAccessor::uuid([], 'id');
    }

    public function testUuidThrowsWhenValueIsNotString(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Row key "id" is not a binary string.');

        RowAccessor::uuid(['id' => 42], 'id');
    }

    public function testUuidTreatsNullAsMissing(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Missing key "id" in row.');

        RowAccessor::uuid(['id' => null], 'id');
    }

    // ───────────── nullableUuid() ─────────────

    public function testNullableUuidReturnsNullForMissingKey(): void
    {
        self::assertNull(RowAccessor::nullableUuid([], 'id'));
    }

    public function testNullableUuidReturnsNullForExplicitNull(): void
    {
        self::assertNull(RowAccessor::nullableUuid(['id' => null], 'id'));
    }

    public function testNullableUuidDecodesBinaryUuidToRfc4122(): void
    {
        $uuid    = Uuid::v7();
        $rfc4122 = $uuid->toRfc4122();
        $row     = ['id' => $uuid->toBinary()];

        self::assertSame($rfc4122, RowAccessor::nullableUuid($row, 'id'));
    }

    public function testNullableUuidThrowsWhenValueIsNotString(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Row key "id" is not a binary string.');

        RowAccessor::nullableUuid(['id' => 42], 'id');
    }
}
