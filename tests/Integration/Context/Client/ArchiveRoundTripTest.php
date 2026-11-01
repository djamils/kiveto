<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Client;

use App\Context\Client\Application\Command\ArchiveClient\ArchiveClient;
use App\Context\Client\Application\Command\CreateClient\ContactMethodDto;
use App\Context\Client\Application\Command\CreateClient\CreateClient;
use App\Context\Client\Application\Command\SetClientConsent\SetClientConsent;
use App\Context\Client\Application\Command\UnarchiveClient\UnarchiveClient;
use App\Context\Client\Application\Query\GetClientConsents\ClientConsentsView;
use App\Context\Client\Application\Query\GetClientConsents\ClientConsentView;
use App\Context\Client\Application\Query\GetClientConsents\GetClientConsents;
use App\Context\Client\Application\Query\GetClientNotificationContact\ClientNotificationContact;
use App\Context\Client\Application\Query\GetClientNotificationContact\GetClientNotificationContact;
use App\Context\Client\Application\Query\ListClientConsentHistory\ConsentHistoryItemView;
use App\Context\Client\Application\Query\ListClientConsentHistory\ListClientConsentHistory;
use App\Context\Client\Domain\Exception\ClientArchivedCannotBeModifiedException;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Pagination\CursorPage;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * S5c — AC 57, 58, 200, 201: archiving withdraws no consent and writes no
 * history; the archived client receives no optional message and cannot be
 * given a consent; unarchived, it has its consents and history back.
 */
final class ArchiveRoundTripTest extends KernelTestCase
{
    private const string CLINIC_ID = '0192a000-0000-7000-8000-0000000c0bc1';

    public function testArchivingThenUnarchivingKeepsTheConsentsAndTheHistory(): void
    {
        $clientId = $this->commands()->dispatch(new CreateClient(
            clinicId: self::CLINIC_ID,
            firstName: 'Hélène',
            lastName: 'Lambert',
            contactMethods: [
                new ContactMethodDto('phone', 'mobile', '+33611223344', true),
                new ContactMethodDto('email', 'home', 'h.lambert@example.com', true),
            ],
            channelsByConsentKind: [
                'marketing'             => ['email'],
                'appointment_reminders' => ['sms'],
                'vaccination_reminders' => ['sms'],
                'billing'               => ['email'],
            ],
            actorType: 'staff',
            actorId: '0192a000-0000-7000-8000-0000000c0ba1',
            authorLabel: 'Julie Martin',
        ));
        \assert(\is_string($clientId));
        $consents = $this->consentsOnFile($this->consents($clientId));
        $history  = $this->history($clientId);
        self::assertCount(4, $history);

        $this->commands()->dispatch(new ArchiveClient(self::CLINIC_ID, $clientId));

        $archived = $this->consents($clientId);
        self::assertTrue($archived->archived);
        self::assertSame($consents, $this->consentsOnFile($archived), 'no consent is withdrawn');
        self::assertSame($history, $this->history($clientId), 'no history row is written');

        $contact = $this->contact($clientId);
        self::assertSame([], $contact->channelsFor('marketing'));
        self::assertSame([], $contact->channelsFor('appointment_reminders'));
        self::assertSame([], $contact->channelsFor('vaccination_reminders'));
        self::assertSame(['email'], $contact->channelsFor('billing'));

        try {
            $this->commands()->dispatch(new SetClientConsent(self::CLINIC_ID, $clientId, 'clinical_documents', ['email'], 'portal', 'portal_user', '0192a000-0000-7000-8000-0000000c0ba2', 'Hélène Lambert'));
            self::fail('No consent is added to an archived client.');
        } catch (ClientArchivedCannotBeModifiedException) {
        }

        $this->commands()->dispatch(new UnarchiveClient(self::CLINIC_ID, $clientId));

        $back = $this->consents($clientId);
        self::assertFalse($back->archived);
        self::assertSame($consents, $this->consentsOnFile($back));
        self::assertSame($history, $this->history($clientId));
        self::assertSame(['email'], $this->contact($clientId)->channelsFor('marketing'));
        self::assertSame(['sms'], $this->contact($clientId)->channelsFor('appointment_reminders'));
    }

    private function consents(string $clientId): ClientConsentsView
    {
        $views = $this->queries()->ask(new GetClientConsents(self::CLINIC_ID, [$clientId]));
        self::assertIsArray($views);
        self::assertInstanceOf(ClientConsentsView::class, $views[$clientId] ?? null);

        return $views[$clientId];
    }

    /**
     * @return array<string, array{list<string>, string}>
     */
    private function consentsOnFile(ClientConsentsView $view): array
    {
        return array_map(
            static fn (ClientConsentView $consent): array => [$consent->channels, $consent->grantedAt->format(\DATE_ATOM)],
            $view->consents,
        );
    }

    /**
     * @return list<string> ids and dates of the rows
     */
    private function history(string $clientId): array
    {
        $page = $this->queries()->ask(new ListClientConsentHistory(self::CLINIC_ID, [$clientId]));
        self::assertInstanceOf(CursorPage::class, $page);

        return array_map(static function (mixed $item): string {
            \assert($item instanceof ConsentHistoryItemView);

            return $item->id . ' ' . $item->at->format(\DATE_ATOM);
        }, $page->items);
    }

    private function contact(string $clientId): ClientNotificationContact
    {
        $contact = $this->queries()->ask(new GetClientNotificationContact(self::CLINIC_ID, $clientId));
        self::assertInstanceOf(ClientNotificationContact::class, $contact);

        return $contact;
    }

    private function commands(): CommandBusInterface
    {
        $bus = self::getContainer()->get(CommandBusInterface::class);
        \assert($bus instanceof CommandBusInterface);

        return $bus;
    }

    private function queries(): QueryBusInterface
    {
        $bus = self::getContainer()->get(QueryBusInterface::class);
        \assert($bus instanceof QueryBusInterface);

        return $bus;
    }
}
