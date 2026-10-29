<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Domain\ValueObject;

use App\Context\Clinic\Domain\Exception\InvalidMessagingSettingsException;
use App\Context\Clinic\Domain\ValueObject\MessagingSettings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MessagingSettingsTest extends TestCase
{
    public function testAValidSenderAndCapAreKeptTrimmed(): void
    {
        $settings = MessagingSettings::create('  Lilas  Vet ', 0);

        self::assertSame('Lilas Vet', $settings->smsSender);
        self::assertSame(0, $settings->overageCapSegments);
        self::assertSame(100000, MessagingSettings::create('Vet 2', 100000)->overageCapSegments);
    }

    /**
     * AC 40b: « Clinique des Lilas » (18 characters) is refused.
     */
    #[DataProvider('provideAMalformedSenderIsRefusedCases')]
    public function testAMalformedSenderIsRefused(string $sender): void
    {
        try {
            MessagingSettings::create($sender, 500);
            self::fail('The sender should have been refused.');
        } catch (InvalidMessagingSettingsException $exception) {
            self::assertSame(InvalidMessagingSettingsException::FIELD_SENDER, $exception->field);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideAMalformedSenderIsRefusedCases(): iterable
    {
        yield 'too long' => ['Clinique des Lilas'];
        yield 'empty' => ['   '];
        yield 'digits only' => ['0142641800'];
        yield 'accent' => ['Vétérinaire'];
        yield 'punctuation' => ['Vet-Lilas'];
    }

    public function testAnImplausibleCapIsRefused(): void
    {
        foreach ([-1, 100001] as $cap) {
            try {
                MessagingSettings::create('Lilas Vet', $cap);
                self::fail('The cap should have been refused.');
            } catch (InvalidMessagingSettingsException $exception) {
                self::assertSame(InvalidMessagingSettingsException::FIELD_CAP, $exception->field);
            }
        }
    }

    #[DataProvider('provideTheDefaultSenderIsDerivedFromTheNameCases')]
    public function testTheDefaultSenderIsDerivedFromTheName(string $name, string $sender): void
    {
        $settings = MessagingSettings::defaultFor($name);

        self::assertSame($sender, $settings->smsSender);
        self::assertSame(MessagingSettings::DEFAULT_OVERAGE_CAP, $settings->overageCapSegments);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideTheDefaultSenderIsDerivedFromTheNameCases(): iterable
    {
        yield 'short' => ['Véto Lyon', 'Veto Lyon'];
        yield 'truncated, no trailing space' => ['Clinique des Lilas', 'Clinique de'];
        yield 'punctuation as spaces' => ["Côte d'Opale", 'Cote d Opal'];
        yield 'nothing usable' => ['123 — 456', 'Kiveto'];
    }

    public function testEquality(): void
    {
        $a = MessagingSettings::create('Lilas Vet', 500);

        self::assertTrue($a->equals(MessagingSettings::create('Lilas Vet', 500)));
        self::assertFalse($a->equals(MessagingSettings::create('Lilas Vet', 501)));
        self::assertFalse($a->equals(MessagingSettings::create('Lilas', 500)));
    }
}
