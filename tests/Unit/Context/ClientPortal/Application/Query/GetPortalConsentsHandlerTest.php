<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Query;

use App\Context\ClientPortal\Application\Port\ClientConsentGrant;
use App\Context\ClientPortal\Application\Port\ClientConsentRecord;
use App\Context\ClientPortal\Application\Query\GetPortalConsents\GetPortalConsents;
use App\Context\ClientPortal\Application\Query\GetPortalConsents\GetPortalConsentsHandler;
use App\Context\ClientPortal\Application\Query\GetPortalConsents\PortalConsent;
use App\Context\ClientPortal\Application\Service\PortalConsentFiles;
use App\Context\ClientPortal\Domain\Exception\ClinicNotLinkedException;
use App\Context\ClientPortal\Domain\Link\ClientLink;
use App\Context\ClientPortal\Domain\Link\ClientLinkId;
use App\Tests\Unit\Context\ClientPortal\Support\InMemoryClientConsents;
use App\Tests\Unit\Context\ClientPortal\Support\InMemoryClientLinks;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/** Task 33: the consents of divergent files are merged (AC 92, AC 204). */
final class GetPortalConsentsHandlerTest extends TestCase
{
    private const string USER   = 'user-1';
    private const string CLINIC = 'clinic-1';

    private InMemoryClientLinks $links;

    private InMemoryClientConsents $consents;

    protected function setUp(): void
    {
        $this->links    = new InMemoryClientLinks();
        $this->consents = new InMemoryClientConsents();
    }

    public function testTheChannelsAreTheUnionOfTheActiveFilesWithTheirLatestDate(): void
    {
        $this->file('client-1', null, [
            new ClientConsentGrant('marketing', ['email'], new \DateTimeImmutable('2026-09-01 08:00:00')),
            new ClientConsentGrant('billing', ['email'], new \DateTimeImmutable('2026-01-10 08:00:00')),
            new ClientConsentGrant('vaccination_reminders', ['phone'], new \DateTimeImmutable('2026-01-10 08:00:00')),
        ]);
        $this->file('client-2', 'sms', [
            new ClientConsentGrant('marketing', ['sms'], new \DateTimeImmutable('2026-03-01 08:00:00')),
            new ClientConsentGrant('appointment_reminders', ['sms'], new \DateTimeImmutable('2026-10-01 08:00:00')),
            new ClientConsentGrant('billing', [], new \DateTimeImmutable('2026-10-20 08:00:00')),
        ]);
        $this->file('client-archived', 'email', [
            new ClientConsentGrant('clinical_documents', ['sms'], new \DateTimeImmutable('2026-10-01 08:00:00')),
        ], archived: true);
        $this->link('client-gone');

        $consents = $this->handler()(new GetPortalConsents(self::USER, self::CLINIC));

        self::assertSame('sms', $consents->preferredChannel);
        self::assertSame([
            ['appointment_reminders', ['sms'], '2026-10-01 08:00', 'send_control'],
            ['vaccination_reminders', [], null, 'send_control'],
            ['clinical_documents', [], null, 'channel_preference'],
            ['billing', ['email'], '2026-01-10 08:00', 'channel_preference'],
            ['marketing', ['sms', 'email'], '2026-09-01 08:00', 'send_control'],
        ], array_map(static fn (PortalConsent $c): array => [$c->kind, $c->channels, $c->grantedAt?->format('Y-m-d H:i'), $c->effect], $consents->consents));
    }

    public function testOnlyArchivedFilesAreNotFound(): void
    {
        $this->file('client-archived', 'email', [], archived: true);
        $this->expectException(ClinicNotLinkedException::class);

        $this->handler()(new GetPortalConsents(self::USER, self::CLINIC));
    }

    private function handler(): GetPortalConsentsHandler
    {
        return new GetPortalConsentsHandler(new PortalConsentFiles($this->links, $this->consents));
    }

    /**
     * @param list<ClientConsentGrant> $grants
     */
    private function file(string $clientId, ?string $preferred, array $grants, bool $archived = false): void
    {
        $this->link($clientId);
        $keyed = [];

        foreach ($grants as $grant) {
            $keyed[$grant->kind] = $grant;
        }

        $this->consents->records[$clientId] = new ClientConsentRecord($clientId, $preferred, $archived, $keyed);
    }

    private function link(string $clientId): void
    {
        $this->links->save(ClientLink::confirm(ClientLinkId::fromString(Uuid::v7()->toRfc4122()), self::USER, self::CLINIC, $clientId, new \DateTimeImmutable('2026-10-01')));
    }
}
