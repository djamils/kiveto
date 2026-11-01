<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Domain;

use App\Context\Client\Domain\ValueObject\CommunicationPreferences;
use App\Context\Client\Domain\ValueObject\Consent;
use App\Context\Client\Domain\ValueObject\ConsentChange;
use App\Context\Client\Domain\ValueObject\ConsentChangeAction;
use App\Context\Client\Domain\ValueObject\ConsentKind;
use App\Context\Client\Domain\ValueObject\ContactChannel;
use PHPUnit\Framework\TestCase;

/**
 * S5b: the history is the diff of two preferences, one change per kind and
 * channel; the same consents give none, whatever their dates.
 */
final class CommunicationPreferencesDiffTest extends TestCase
{
    public function testEveryChannelGrantedAndWithdrawnIsOneChange(): void
    {
        $before = new CommunicationPreferences(ContactChannel::SMS, [
            new Consent(ConsentKind::MARKETING, [ContactChannel::SMS, ContactChannel::EMAIL], new \DateTimeImmutable('2025-01-01')),
            new Consent(ConsentKind::BILLING, [ContactChannel::EMAIL], new \DateTimeImmutable('2025-01-01')),
        ]);
        $after = new CommunicationPreferences(ContactChannel::SMS, [
            new Consent(ConsentKind::APPOINTMENT_REMINDERS, [ContactChannel::SMS], new \DateTimeImmutable('2026-01-01')),
            new Consent(ConsentKind::BILLING, [ContactChannel::SMS], new \DateTimeImmutable('2026-01-01')),
            new Consent(ConsentKind::MARKETING, [ContactChannel::SMS], new \DateTimeImmutable('2025-01-01')),
        ]);

        self::assertSame([
            [ConsentKind::APPOINTMENT_REMINDERS, ContactChannel::SMS, ConsentChangeAction::GRANTED],
            [ConsentKind::BILLING, ContactChannel::SMS, ConsentChangeAction::GRANTED],
            [ConsentKind::BILLING, ContactChannel::EMAIL, ConsentChangeAction::WITHDRAWN],
            [ConsentKind::MARKETING, ContactChannel::EMAIL, ConsentChangeAction::WITHDRAWN],
        ], array_map(
            static fn (ConsentChange $change): array => [$change->kind, $change->channel, $change->action],
            $before->changesTo($after),
        ));
    }

    public function testTheSameConsentsGiveNoChange(): void
    {
        $before = new CommunicationPreferences(null, [
            new Consent(ConsentKind::MARKETING, [ContactChannel::SMS], new \DateTimeImmutable('2025-01-01')),
        ]);
        $after = new CommunicationPreferences(ContactChannel::EMAIL, [
            new Consent(ConsentKind::MARKETING, [ContactChannel::SMS], new \DateTimeImmutable('2026-01-01')),
        ]);

        self::assertSame([], $before->changesTo($after));
        self::assertSame([], CommunicationPreferences::none()->changesTo(CommunicationPreferences::none()));
    }

    public function testWithConsentChangesOneKindOnly(): void
    {
        $grantedAt = new \DateTimeImmutable('2025-01-01');
        $now       = new \DateTimeImmutable('2026-10-01');
        $before    = new CommunicationPreferences(ContactChannel::PHONE, [
            new Consent(ConsentKind::MARKETING, [ContactChannel::SMS, ContactChannel::EMAIL], $grantedAt),
            new Consent(ConsentKind::BILLING, [ContactChannel::EMAIL], $grantedAt),
        ]);

        $narrowed = $before->withConsent(ConsentKind::MARKETING, [ContactChannel::SMS], $now);

        self::assertSame(ContactChannel::PHONE, $narrowed->preferredChannel);
        self::assertSame([ContactChannel::SMS], $narrowed->consentFor(ConsentKind::MARKETING)?->channels);
        self::assertSame($grantedAt, $narrowed->consentFor(ConsentKind::MARKETING)->grantedAt, 'narrowing keeps the date');
        self::assertSame($grantedAt, $narrowed->consentFor(ConsentKind::BILLING)?->grantedAt);

        $withdrawn = $before->withConsent(ConsentKind::MARKETING, [], $now);
        self::assertNull($withdrawn->consentFor(ConsentKind::MARKETING));
        self::assertNotNull($withdrawn->consentFor(ConsentKind::BILLING));

        $extended = $before->withConsent(ConsentKind::BILLING, [ContactChannel::EMAIL, ContactChannel::SMS], $now);
        self::assertSame($now, $extended->consentFor(ConsentKind::BILLING)?->grantedAt, 'a new channel is a new consent');
    }
}
