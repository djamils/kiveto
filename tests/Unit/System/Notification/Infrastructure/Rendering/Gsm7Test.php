<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Notification\Infrastructure\Rendering;

use App\System\Notification\Domain\Message\SmsEncoding;
use App\System\Notification\Infrastructure\Rendering\Gsm7;
use App\System\Notification\Infrastructure\Rendering\SmsSegmentCounter;
use PHPUnit\Framework\TestCase;

final class Gsm7Test extends TestCase
{
    /**
     * AC 6: variable data is transliterated, GSM-7 accented letters are kept.
     */
    public function testVariableDataIsTransliterated(): void
    {
        self::assertSame('Chloe', Gsm7::transliterate('Chloë'));
        self::assertSame("Cote d'Opale", Gsm7::transliterate('Côte d’Opale'));
        self::assertSame('Élodie à Noel', Gsm7::transliterate('Élodie à Noël'));
        self::assertSame('Ça va, Ñandu, ÄÖÜ äöü ñ ù ì ò è é', Gsm7::transliterate('Ça va, Ñandú, ÄÖÜ äöü ñ ù ì ò è é'));
        self::assertSame('oe æ c', Gsm7::transliterate('œ æ ç'));
        self::assertSame('?', Gsm7::transliterate('🐶'));
    }

    public function testTheAlphabetIsKnown(): void
    {
        self::assertTrue(Gsm7::isGsm7("@£\$¥ {}[]~|^€\\ \n"));
        self::assertFalse(Gsm7::isGsm7('Côte'));
        self::assertSame(3, Gsm7::length('a€'));
        self::assertNull(Gsm7::length('ô'));
    }

    /**
     * AC 7: « € » counts two characters.
     */
    public function testSegmentsAreCounted(): void
    {
        self::assertSame([SmsEncoding::GSM_7, 1], SmsSegmentCounter::count(str_repeat('a', 160)));
        self::assertSame([SmsEncoding::GSM_7, 2], SmsSegmentCounter::count(str_repeat('a', 159) . '€'));
        self::assertSame([SmsEncoding::GSM_7, 2], SmsSegmentCounter::count(str_repeat('a', 306)));
        self::assertSame([SmsEncoding::GSM_7, 3], SmsSegmentCounter::count(str_repeat('a', 307)));
        self::assertSame([SmsEncoding::GSM_7, 1], SmsSegmentCounter::count(''));
        self::assertSame([SmsEncoding::UCS_2, 1], SmsSegmentCounter::count(str_repeat('ô', 70)));
        self::assertSame([SmsEncoding::UCS_2, 2], SmsSegmentCounter::count(str_repeat('ô', 71)));
    }

    /**
     * N14: a two-septet € or a two-unit emoji never straddles two parts.
     */
    public function testACharacterIsNeverSplitAcrossSegments(): void
    {
        self::assertSame([SmsEncoding::GSM_7, 1], SmsSegmentCounter::count(str_repeat('a', 152) . '€a'));
        self::assertSame([SmsEncoding::GSM_7, 3], SmsSegmentCounter::count(str_repeat('a', 152) . '€' . str_repeat('a', 152)));
        self::assertSame([SmsEncoding::GSM_7, 2], SmsSegmentCounter::count(str_repeat('a', 151) . '€' . str_repeat('a', 153)));
        self::assertSame([SmsEncoding::UCS_2, 3], SmsSegmentCounter::count(str_repeat('ô', 66) . '😀' . str_repeat('ô', 66)));
        self::assertSame([SmsEncoding::UCS_2, 1], SmsSegmentCounter::count(str_repeat('ô', 68) . '😀'));
        self::assertSame([SmsEncoding::UCS_2, 3], SmsSegmentCounter::count(str_repeat('ô', 135)));
        self::assertSame([SmsEncoding::UCS_2, 1], SmsSegmentCounter::count('🐶'), 'a surrogate pair takes two units');
    }
}
