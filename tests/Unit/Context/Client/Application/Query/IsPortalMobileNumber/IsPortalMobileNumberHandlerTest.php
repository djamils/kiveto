<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Application\Query\IsPortalMobileNumber;

use App\Context\Client\Application\Query\IsPortalMobileNumber\IsPortalMobileNumber;
use App\Context\Client\Application\Query\IsPortalMobileNumber\IsPortalMobileNumberHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IsPortalMobileNumberHandlerTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function provideTheMobileNumberPolicyDecidesCases(): iterable
    {
        yield 'metropolitan 06' => ['+33612345678', true];
        yield 'metropolitan 07' => ['+33712345678', true];
        yield 'La Réunion mobile' => ['+262692123456', true];
        yield 'Wallis-et-Futuna' => ['+681821234', true];
        yield 'metropolitan landline' => ['+33142641800', false];
        yield 'foreign mobile' => ['+32470123456', false];
        yield 'La Réunion landline' => ['+262262123456', false];
    }

    #[DataProvider('provideTheMobileNumberPolicyDecidesCases')]
    public function testTheMobileNumberPolicyDecides(string $phone, bool $expected): void
    {
        self::assertSame($expected, (new IsPortalMobileNumberHandler())(new IsPortalMobileNumber($phone)));
    }
}
