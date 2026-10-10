<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Domain\ValueObject;

use App\Context\Client\Domain\ValueObject\CommunicationPreferences;
use App\Context\Client\Domain\ValueObject\Consent;
use App\Context\Client\Domain\ValueObject\ConsentKind;
use App\Context\Client\Domain\ValueObject\ContactChannel;
use PHPUnit\Framework\TestCase;

final class CommunicationPreferencesTest extends TestCase
{
    public function testConsentKeepsDistinctAutomatedChannels(): void
    {
        $grantedAt = new \DateTimeImmutable('2026-01-04');
        $consent   = new Consent(
            ConsentKind::APPOINTMENT_REMINDERS,
            [ContactChannel::SMS, ContactChannel::EMAIL, ContactChannel::SMS],
            $grantedAt,
        );

        self::assertSame(ConsentKind::APPOINTMENT_REMINDERS, $consent->kind);
        self::assertSame([ContactChannel::SMS, ContactChannel::EMAIL], $consent->channels);
        self::assertSame($grantedAt, $consent->grantedAt);
        self::assertTrue($consent->allows(ContactChannel::SMS));
        self::assertFalse($consent->allows(ContactChannel::PHONE));
    }

    public function testConsentRejectsAChannelThatCannotBeAutomated(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Consent(ConsentKind::BILLING, [ContactChannel::PHONE], new \DateTimeImmutable());
    }

    public function testConsentRejectsAnEmptyChannelList(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Consent(ConsentKind::BILLING, [], new \DateTimeImmutable());
    }

    public function testNoneHoldsNothing(): void
    {
        $preferences = CommunicationPreferences::none();

        self::assertNull($preferences->preferredChannel);
        self::assertSame([], $preferences->consents);
        self::assertNull($preferences->consentFor(ConsentKind::MARKETING));
    }

    public function testRejectsTheSameConsentKindTwice(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CommunicationPreferences(null, [
            new Consent(ConsentKind::BILLING, [ContactChannel::SMS], new \DateTimeImmutable()),
            new Consent(ConsentKind::BILLING, [ContactChannel::EMAIL], new \DateTimeImmutable()),
        ]);
    }

    public function testReviseGrantsNewConsentsAtTheGivenDate(): void
    {
        $now = new \DateTimeImmutable('2026-09-30 11:00:00');

        $revised = CommunicationPreferences::none()->revise(
            ContactChannel::PHONE,
            [
                ConsentKind::APPOINTMENT_REMINDERS->value => [ContactChannel::SMS],
                ConsentKind::MARKETING->value             => [],
            ],
            $now,
        );

        self::assertSame(ContactChannel::PHONE, $revised->preferredChannel);
        self::assertCount(1, $revised->consents);

        $consent = $revised->consentFor(ConsentKind::APPOINTMENT_REMINDERS);
        self::assertNotNull($consent);
        self::assertSame($now, $consent->grantedAt);
        self::assertSame([ContactChannel::SMS], $consent->channels);
        self::assertNull($revised->consentFor(ConsentKind::MARKETING));
    }

    public function testReviseKeepsTheOriginalDateOfAConsentAndWithdrawsTheOthers(): void
    {
        $grantedAt = new \DateTimeImmutable('2019-03-12');
        $current   = new CommunicationPreferences(ContactChannel::SMS, [
            new Consent(ConsentKind::APPOINTMENT_REMINDERS, [ContactChannel::SMS, ContactChannel::EMAIL], $grantedAt),
            new Consent(ConsentKind::BILLING, [ContactChannel::EMAIL], $grantedAt),
        ]);

        $revised = $current->revise(
            null,
            [ConsentKind::APPOINTMENT_REMINDERS->value => [ContactChannel::SMS]],
            new \DateTimeImmutable('2026-09-30'),
        );

        self::assertNull($revised->preferredChannel);

        $consent = $revised->consentFor(ConsentKind::APPOINTMENT_REMINDERS);
        self::assertNotNull($consent);
        self::assertSame($grantedAt, $consent->grantedAt);
        self::assertSame([ContactChannel::SMS], $consent->channels);
        self::assertNull($revised->consentFor(ConsentKind::BILLING));
    }

    public function testReviseDatesAConsentAgainWhenItCoversANewChannel(): void
    {
        $now     = new \DateTimeImmutable('2026-09-30');
        $current = new CommunicationPreferences(null, [
            new Consent(ConsentKind::APPOINTMENT_REMINDERS, [ContactChannel::EMAIL], new \DateTimeImmutable('2019-03-12')),
        ]);

        $revised = $current->revise(
            null,
            [ConsentKind::APPOINTMENT_REMINDERS->value => [ContactChannel::EMAIL, ContactChannel::SMS]],
            $now,
        );

        self::assertSame($now, $revised->consentFor(ConsentKind::APPOINTMENT_REMINDERS)?->grantedAt);
    }
}
