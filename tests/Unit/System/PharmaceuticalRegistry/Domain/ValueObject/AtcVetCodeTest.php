<?php

declare(strict_types=1);

namespace Tests\Unit\System\PharmaceuticalRegistry\Domain\ValueObject;

use App\System\PharmaceuticalRegistry\Domain\Exception\InvalidAtcVetCodeException;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\AtcVetCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AtcVetCodeTest extends TestCase
{
    #[DataProvider('provideAcceptsValidCodesCases')]
    public function testAcceptsValidCodes(string $code): void
    {
        self::assertSame($code, AtcVetCode::fromString($code)->toString());
    }

    /** @return array<array{string}> */
    public static function provideAcceptsValidCodesCases(): iterable
    {
        return [['QD11AA'], ['QI07'], ['QP54AB'], ['QJ01AA01']];
    }

    #[DataProvider('provideRejectsInvalidCodesCases')]
    public function testRejectsInvalidCodes(string $code): void
    {
        $this->expectException(InvalidAtcVetCodeException::class);
        AtcVetCode::fromString($code);
    }

    /** @return array<array{string}> */
    public static function provideRejectsInvalidCodesCases(): iterable
    {
        return [['D11'], ['X11AA'], ['QD11AAAA1'], ['qd11aa']];
    }

    public function testMainGroup(): void
    {
        $code = AtcVetCode::fromString('QD11AA');

        self::assertSame('QD', $code->mainGroup());
    }
}
