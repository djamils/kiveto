<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Client\Story;

use App\Context\Client\Application\Command\ArchiveClient\ArchiveClient;
use App\Context\Client\Application\Command\SetClientConsent\SetClientConsent;
use App\Context\Client\Application\Command\UnarchiveClient\UnarchiveClient;
use App\Context\Client\Domain\Client;
use App\Context\Client\Domain\Repository\ClientRepositoryInterface;
use App\Context\Client\Domain\Repository\ConsentHistoryRepositoryInterface;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Context\Client\Domain\ValueObject\ConsentActor;
use App\Context\Client\Domain\ValueObject\ConsentActorType;
use App\Context\Client\Domain\ValueObject\ConsentChange;
use App\Context\Client\Domain\ValueObject\ConsentChangeAction;
use App\Context\Client\Domain\ValueObject\ConsentHistoryEntry;
use App\Context\Client\Domain\ValueObject\ConsentKind;
use App\Context\Client\Domain\ValueObject\ConsentSource;
use App\Context\Client\Domain\ValueObject\ContactChannel;
use App\Context\Client\Domain\ValueObject\ContactLabel;
use App\Fixtures\Context\Animal\Story\PortalPetsAnimalStory;
use App\Fixtures\Context\Client\Factory\ClientEntityFactory;
use App\Fixtures\Context\Client\Factory\ContactMethodEntityFactory;
use App\Fixtures\Context\ClientPortal\Story\ClientPortalStatesStory;
use App\Fixtures\Context\ClientPortal\Story\PortalDocumentsStory;
use App\Fixtures\Context\Clinic\Story\ClinicDataStory;
use App\Fixtures\System\IdentityAccess\Story\PortalUserStory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Story;

/**
 * The consents of the Portal owners and their history (S5b, S5c).
 *
 * | Client file                             | Consents now                                   | History                                                       |
 * |-----------------------------------------|------------------------------------------------|---------------------------------------------------------------|
 * | Julien Girard, Paris (demo account)     | **all five** kinds, by SMS                     | clinic (Inès Moreau, at the link), then two Portal grants     |
 * | Julien Girard, Lyon (demo account)      | **none**                                       | « Reprise » (approximate), then withdrawn by the clinic       |
 * | Sophie Rey, Paris                       | **partial**: billing, documents, RDV reminders | clinic a year ago, the RDV reminders on the Portal today      |
 * | Thierry Marchand, Paris (first file)    | appointments SMS + e-mail, documents, billing, | « Reprise » (approximate), clinic, then Portal (both files)   |
 * |                                         | marketing e-mail, vaccinations SMS             |                                                               |
 * | Thierry Marchand, Paris (second file)   | appointments, billing, vaccinations, SMS only  | « Reprise » (approximate), clinic, then Portal (both files)   |
 * | Nathalie Caron, Paris (archived)        | marketing, reminders, billing — kept, but no   | clinic two years ago; archived after (no history row)         |
 * |                                         | optional message leaves (`channelsFor()`)      |                                                               |
 * | Pierre Caron, Paris (her twin)          | the same, active again                         | the same; archived then unarchived: everything back           |
 *
 * Marchand's two files diverge: the Portal shows the union of their
 * channels. Every row is written by the Client aggregate's diff (the
 * repository appends what `pullConsentHistory()` gives), dated in the past
 * so that the history reads as it would have happened; Sophie Rey's
 * change of today goes through the `SetClientConsent` command. The
 * « Reprise » rows are what the history migration writes for a consent on
 * file before the history existed: on a fresh database it has nothing to
 * take over, so they are appended here through the history repository.
 */
final class ConsentsStory extends Story
{
    public const string NATHALIE_CARON_ID = '019a3d00-0000-7000-8000-0000000000e1';
    public const string PIERRE_CARON_ID   = '019a3d00-0000-7000-8000-0000000000e2';

    private const string PARIS = ClinicDataStory::INDEPENDENT_CLINIC_ID;
    private const string LYON  = ClinicDataStory::GROUP_CLINIC_ID;

    private \DateTimeImmutable $now;

    public function __construct(
        private readonly ClientRepositoryInterface $clients,
        private readonly ConsentHistoryRepositoryInterface $history,
        private readonly DomainEventPublisher $events,
        private readonly CommandBusInterface $commandBus,
        private readonly EntityManagerInterface $entityManager,
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
    }

    public function build(): void
    {
        PortalDocumentsStory::load();
        PortalPetsAnimalStory::load();

        $this->now = $this->clock->now();

        $this->allConsents();
        $this->noConsent();
        $this->partialConsents();
        $this->twoDivergentFiles();
        $this->archivedTwins();
    }

    /** Julien Girard in Paris: the clinic at the link, then the owner on the Portal. */
    private function allConsents(): void
    {
        $client = $this->clientOf(PortalUserStory::DEMO_PHONE, self::PARIS);
        $owner  = $this->owner(PortalUserStory::DEMO_PHONE, 'Julien Girard');

        $this->sheet(self::PARIS, $client, '-61 days', 'sms', [
            'appointment_reminders' => ['sms'],
            'clinical_documents'    => ['sms'],
            'billing'               => ['sms'],
        ], $this->staff('accueil@kiveto.local', 'Inès Moreau'));
        $this->portal(self::PARIS, $client, '-30 days', 'vaccination_reminders', ['sms'], $owner);
        $this->portal(self::PARIS, $client, '-3 days', 'marketing', ['sms'], $owner);
    }

    /** Julien Girard in Lyon: the reminder on file before the history, withdrawn at the clinic. */
    private function noConsent(): void
    {
        $client = ClientPortalStatesStory::GIRARD_LYON_ID;

        $this->takeOver(self::LYON, $client);
        $this->sheet(self::LYON, $client, '-40 days', 'sms', [], $this->staff('admin.clinic@kiveto.local', 'Claire Lambert'));
    }

    /** Sophie Rey: billing and documents by e-mail from the clinic, the RDV reminders asked on the Portal today. */
    private function partialConsents(): void
    {
        $client = $this->clientOf(PortalDocumentsStory::REY_PHONE, self::PARIS);

        $this->sheet(self::PARIS, $client, '-1 year', 'email', [
            'clinical_documents' => ['email'],
            'billing'            => ['email'],
        ], $this->staff('accueil@kiveto.local', 'Inès Moreau'));

        $this->commandBus->dispatch(new SetClientConsent(
            clinicId: self::PARIS,
            clientId: $client,
            kind: 'appointment_reminders',
            channels: ['sms'],
            source: 'portal',
            actorType: 'portal_user',
            actorId: $this->portalUserId(PortalDocumentsStory::REY_PHONE),
            authorLabel: 'Sophie Rey',
        ));
    }

    /**
     * Thierry Marchand's two Paris files: different consents at the clinic,
     * then one Portal change written to both (the Portal writes every
     * active linked file of the clinic).
     */
    private function twoDivergentFiles(): void
    {
        $first  = ClientSheetStatesStory::CLIENT_NORMAL_ID;
        $second = PortalPetsAnimalStory::MARCHAND_SECOND_FILE_ID;
        $desk   = $this->staff('accueil@kiveto.local', 'Inès Moreau');
        $owner  = $this->owner(PortalDocumentsStory::MARCHAND_PHONE, 'Thierry Marchand');

        $this->takeOver(self::PARIS, $first);
        $this->takeOver(self::PARIS, $second);

        $this->sheet(self::PARIS, $first, '-90 days', 'sms', [
            'appointment_reminders' => ['sms', 'email'],
            'clinical_documents'    => ['email'],
            'billing'               => ['sms', 'email'],
            'marketing'             => ['email'],
        ], $desk);
        $this->sheet(self::PARIS, $second, '-20 days', 'sms', [
            'appointment_reminders' => ['sms'],
            'billing'               => ['sms'],
        ], $desk);

        foreach ([$first, $second] as $file) {
            $this->portal(self::PARIS, $file, '-5 days', 'vaccination_reminders', ['sms'], $owner);
        }
    }

    /**
     * Two clients with the same optional consents: Nathalie's file is
     * archived (nothing optional reaches her, her consents stay), Pierre's
     * was archived then unarchived (everything back, no history row).
     */
    private function archivedTwins(): void
    {
        $twins = [
            self::NATHALIE_CARON_ID => ['madam', 'Nathalie', '+33656781234', 'nathalie.caron@sfr.fr'],
            self::PIERRE_CARON_ID   => ['mister', 'Pierre', '+33656781235', 'pierre.caron@sfr.fr'],
        ];

        foreach ($twins as $id => [$civility, $firstName, $phone, $email]) {
            $this->client($id, $civility, $firstName, 'Caron', $phone, $email);
            $this->sheet(self::PARIS, $id, '-2 years', 'email', [
                'appointment_reminders' => ['sms'],
                'vaccination_reminders' => ['sms'],
                'marketing'             => ['email'],
                'billing'               => ['email'],
            ], $this->staff('accueil@kiveto.local', 'Inès Moreau'));
            $this->commandBus->dispatch(new ArchiveClient(self::PARIS, $id));
        }

        $this->commandBus->dispatch(new UnarchiveClient(self::PARIS, self::PIERRE_CARON_ID));
    }

    /**
     * The clinic's sheet: the preferred channel and every consent at once.
     *
     * @param array<string, list<string>> $channelsByKind
     */
    private function sheet(string $clinicId, string $clientId, string $when, ?string $preferred, array $channelsByKind, ConsentActor $actor): void
    {
        $this->change($clinicId, $clientId, function (Client $client) use ($when, $preferred, $channelsByKind, $actor): void {
            $client->updateCommunicationPreferences(
                null === $preferred ? null : ContactChannel::from($preferred),
                array_map(static fn (array $channels): array => array_map(ContactChannel::from(...), $channels), $channelsByKind),
                $this->now->modify($when),
                ConsentSource::CLINIC,
                $actor,
            );
        });
    }

    /**
     * The owner's switch on the Portal: one kind.
     *
     * @param list<string> $channels
     */
    private function portal(string $clinicId, string $clientId, string $when, string $kind, array $channels, ConsentActor $owner): void
    {
        $this->change($clinicId, $clientId, function (Client $client) use ($when, $kind, $channels, $owner): void {
            $client->setConsent(ConsentKind::from($kind), array_map(ContactChannel::from(...), $channels), $this->now->modify($when), ConsentSource::PORTAL, $owner);
        });
    }

    /** As the command handlers do: the client locked, changed, saved with its history rows, its events published. */
    private function change(string $clinicId, string $clientId, \Closure $change): void
    {
        $this->entityManager->wrapInTransaction(function () use ($clinicId, $clientId, $change): void {
            $client = $this->clients->getForUpdate(ClinicId::fromString($clinicId), ClientId::fromString($clientId));
            $change($client);
            $this->clients->save($client);
            $this->events->publish($client);
        });
    }

    /**
     * What the history migration writes for a consent on file before the
     * history: one approximate grant per channel, by the system, dated by
     * its grantedAt (an upper bound).
     */
    private function takeOver(string $clinicId, string $clientId): void
    {
        $client  = $this->clients->get(ClinicId::fromString($clinicId), ClientId::fromString($clientId));
        $entries = [];

        foreach ($client->communicationPreferences()->consents as $consent) {
            foreach ($consent->channels as $channel) {
                $entries[] = new ConsentHistoryEntry(
                    $client->clinicId(),
                    $client->id(),
                    new ConsentChange($consent->kind, $channel, ConsentChangeAction::GRANTED),
                    $consent->grantedAt,
                    ConsentSource::SYSTEM,
                    new ConsentActor(ConsentActorType::SYSTEM, null, 'Reprise'),
                    approximate: true,
                );
            }
        }

        \assert([] !== $entries, \sprintf('Client %s has no consent on file to take over.', $clientId));
        $this->history->append($entries);
    }

    private function client(string $id, string $civility, string $firstName, string $lastName, string $phone, string $email): void
    {
        $since = $this->now->modify('-4 years')->format('Y-m-d H:i:s');

        ClientEntityFactory::new()
            ->withId($id)
            ->withClinicId(self::PARIS)
            ->withName($firstName, $lastName)
            ->withCivility($civility)
            ->since($since)
            ->withPostalAddress(streetLine1: '18 rue Championnet', city: 'Paris', countryCode: 'FR', postalCode: '75018')
            ->active()
            ->create()
        ;

        ContactMethodEntityFactory::new()->forClient($id)->phone($phone)->with(['label' => ContactLabel::MOBILE])->primary()->create();
        ContactMethodEntityFactory::new()->forClient($id)->email($email)->home()->primary()->create();
    }

    private function staff(string $email, string $name): ConsentActor
    {
        $id = $this->connection->fetchOne("SELECT BIN_TO_UUID(id) FROM identity_access__users WHERE type = 'CLINIC' AND email = ?", [$email]);
        \assert(\is_string($id), \sprintf('The clinic dataset creates the staff member %s.', $email));

        return new ConsentActor(ConsentActorType::STAFF, $id, $name);
    }

    private function owner(string $phone, string $name): ConsentActor
    {
        return new ConsentActor(ConsentActorType::PORTAL_USER, $this->portalUserId($phone), $name);
    }

    private function portalUserId(string $phone): string
    {
        $id = $this->connection->fetchOne("SELECT BIN_TO_UUID(id) FROM identity_access__users WHERE type = 'PORTAL' AND phone = ?", [$phone]);
        \assert(\is_string($id), \sprintf('The Portal dataset creates the owner account %s.', $phone));

        return $id;
    }

    /** The client file of the clinic the owner account is linked to (the oldest link). */
    private function clientOf(string $phone, string $clinicId): string
    {
        $id = $this->connection->fetchOne(
            'SELECT BIN_TO_UUID(l.client_id) FROM client_portal__client_links l
             WHERE l.user_id = ? AND l.clinic_id = ? AND l.revoked_at IS NULL ORDER BY l.linked_at, l.id LIMIT 1',
            [Uuid::fromString($this->portalUserId($phone))->toBinary(), Uuid::fromString($clinicId)->toBinary()],
        );
        \assert(\is_string($id), \sprintf('The account %s is linked in clinic %s.', $phone, $clinicId));

        return $id;
    }
}
