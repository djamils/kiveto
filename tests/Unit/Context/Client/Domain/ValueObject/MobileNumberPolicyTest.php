<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Domain\ValueObject;

use App\Context\Client\Domain\ValueObject\ContactLabel;
use App\Context\Client\Domain\ValueObject\MobileNumberPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MobileNumberPolicyTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function provideTellsAMobileByItsPrefixCases(): iterable
    {
        yield 'France 06' => ['+33612345678', true];
        yield 'France 07' => ['+33712345678', true];
        yield 'France landline' => ['+33142641800', false];
        yield 'La Réunion mobile' => ['+262692123456', true];
        yield 'La Réunion landline' => ['+262262123456', false];
        yield 'Mayotte mobile' => ['+262639123456', true];
        yield 'Guadeloupe mobile' => ['+590690123456', true];
        yield 'Guadeloupe mobile 0691' => ['+590691123456', true];
        yield 'Guadeloupe landline' => ['+590590123456', false];
        yield 'Guyane mobile' => ['+594694123456', true];
        yield 'Martinique mobile' => ['+596696123456', true];
        yield 'Martinique mobile 0697' => ['+596697123456', true];
        yield 'Saint-Pierre mobile' => ['+50855123456', true];
        yield 'Nouvelle-Calédonie 7' => ['+687751234', true];
        yield 'Nouvelle-Calédonie 8' => ['+687851234', true];
        yield 'Nouvelle-Calédonie 9' => ['+687951234', true];
        yield 'Nouméa landline' => ['+687251234', false];
        yield 'Polynésie 87' => ['+68987123456', true];
        yield 'Polynésie 88' => ['+68988123456', true];
        yield 'Polynésie 89' => ['+68989123456', true];
        yield 'Papeete landline' => ['+68940123456', false];
        yield 'Wallis-et-Futuna' => ['+681821234', true];
        yield 'spaces are ignored' => ['+33 6 12 34 56 78', true];
        yield 'foreign number' => ['+4915112345678', false];
    }

    #[DataProvider('provideTellsAMobileByItsPrefixCases')]
    public function testTellsAMobileByItsPrefix(string $number, bool $mobile): void
    {
        self::assertSame($mobile, MobileNumberPolicy::isMobile($number));
    }

    public function testAPhoneLabelledMobileCanReceiveAnSmsUnlessItsPrefixIsALandline(): void
    {
        self::assertTrue(MobileNumberPolicy::canReceiveSms('+4915112345678', ContactLabel::MOBILE));
        self::assertTrue(MobileNumberPolicy::canReceiveSms('+33612345678', ContactLabel::HOME));
        self::assertFalse(MobileNumberPolicy::canReceiveSms('+33142641800', ContactLabel::HOME));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideALandlineLabelledMobileCannotReceiveAnSmsCases(): iterable
    {
        yield '01' => ['+33142641800'];
        yield '02' => ['+33240123456'];
        yield '03' => ['+33380123456'];
        yield '04' => ['+33491123456'];
        yield '05' => ['+33556123456'];
        yield '09' => ['+33 9 70 12 34 56'];
    }

    #[DataProvider('provideALandlineLabelledMobileCannotReceiveAnSmsCases')]
    public function testALandlineLabelledMobileCannotReceiveAnSms(string $number): void
    {
        self::assertTrue(MobileNumberPolicy::isLandline($number));
        self::assertFalse(MobileNumberPolicy::canReceiveSms($number, ContactLabel::MOBILE));
    }
}
