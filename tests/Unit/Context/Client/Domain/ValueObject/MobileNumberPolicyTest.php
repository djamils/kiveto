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
        yield 'Guadeloupe landline' => ['+590590123456', false];
        yield 'Guyane mobile' => ['+594694123456', true];
        yield 'Martinique mobile' => ['+596696123456', true];
        yield 'Saint-Pierre mobile' => ['+50855123456', true];
        yield 'Nouvelle-Calédonie 7' => ['+687751234', true];
        yield 'Nouvelle-Calédonie 8' => ['+687851234', true];
        yield 'Nouvelle-Calédonie 9' => ['+687951234', true];
        yield 'Nouméa landline' => ['+687251234', false];
        yield 'spaces are ignored' => ['+33 6 12 34 56 78', true];
        yield 'foreign number' => ['+4915112345678', false];
    }

    #[DataProvider('provideTellsAMobileByItsPrefixCases')]
    public function testTellsAMobileByItsPrefix(string $number, bool $mobile): void
    {
        self::assertSame($mobile, MobileNumberPolicy::isMobile($number));
    }

    public function testAPhoneLabelledMobileCanReceiveAnSmsWhateverItsPrefix(): void
    {
        self::assertTrue(MobileNumberPolicy::canReceiveSms('+4915112345678', ContactLabel::MOBILE));
        self::assertTrue(MobileNumberPolicy::canReceiveSms('+33612345678', ContactLabel::HOME));
        self::assertFalse(MobileNumberPolicy::canReceiveSms('+33142641800', ContactLabel::HOME));
    }
}
