<?php

declare(strict_types=1);

namespace App\Fixtures\System\Notification\Story;

use App\Context\Animal\Application\Command\CreateAnimal\CreateAnimal;
use App\Context\Billing\Application\Command\AddManualInvoiceLine\AddManualInvoiceLine;
use App\Context\Billing\Application\Command\IssueInvoice\IssueInvoice;
use App\Context\Billing\Application\Command\PrepareInvoiceForAdmission\PrepareInvoiceForAdmission;
use App\Context\Billing\Application\Command\RemindUnpaidInvoices\RemindUnpaidInvoices;
use App\Context\Billing\Application\Command\SendInvoiceToClient\SendInvoiceToClient;
use App\Context\Client\Application\Command\CreateClient\ContactMethodDto;
use App\Context\Client\Application\Command\CreateClient\CreateClient;
use App\Context\Client\Application\Command\ReplaceClientContactMethods\ContactMethodDto as ReplacementContactMethod;
use App\Context\Client\Application\Command\ReplaceClientContactMethods\ReplaceClientContactMethods;
use App\Context\Client\Application\Command\UpdateClientCommunicationPreferences\UpdateClientCommunicationPreferences;
use App\Context\Clinic\Application\Command\Clinic\SyncAllClinicMessagingProfiles\SyncAllClinicMessagingProfiles;
use App\Context\Clinic\Application\Command\Clinic\UpdateClinicMessagingSettings\UpdateClinicMessagingSettings;
use App\Context\Clinic\Application\Command\Staff\OnboardStaffMember\OnboardStaffMember;
use App\Context\Clinic\Application\Query\Clinic\GetClinicSettings\ClinicSettingsView;
use App\Context\Clinic\Application\Query\Clinic\GetClinicSettings\GetClinicSettings;
use App\Context\Clinic\Application\Query\Staff\GetStaffPrintedName\GetStaffPrintedName;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipEngagement;
use App\Context\Clinic\Domain\Staff\ValueObject\ProfessionalTitle;
use App\Context\Inventory\Application\Command\NotifyExpiringLots\NotifyExpiringLots;
use App\Context\Scheduling\Application\Command\RequestDueReminders\RequestDueReminders;
use App\Context\Scheduling\Application\Command\ScheduleAppointment\ScheduleAppointment;
use App\Fixtures\Context\Clinic\Story\ClinicDataStory;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use App\Fixtures\System\Notification\Factory\OutboundMessageEntityFactory;
use App\Presentation\Clinic\ViewModel\Team\TeamPageBuilder;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use App\System\IdentityAccess\Application\Command\RegisterInvitedUser\InvitedUserRegistration;
use App\System\IdentityAccess\Application\Command\RegisterInvitedUser\RegisterInvitedUser;
use App\System\IdentityAccess\Application\Command\SendInvitation\SendInvitation;
use App\System\Notification\Application\Command\MarkInAppNotificationRead\MarkInAppNotificationRead;
use App\System\Notification\Application\Query\GetSmsUsage\GetSmsUsage;
use App\System\Notification\Application\Query\GetSmsUsage\SmsUsageView;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\DependencyInjection\ServicesResetterInterface;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\EventListener\ResetServicesListener;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Worker;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Story;

/**
 * The notifications demo (simulator delivery, Mailpit for the e-mails):
 *
 * - every clinic has its messaging profile, and every future appointment of
 *   the stories above has had its confirmation (the debounced syncs they
 *   queued are played here, not left to a dev worker);
 * - Lyon has used ~85 % of its SMS quota (« 80 % » banner and bell), the
 *   Calanques are over quota with a small overage cap already reached
 *   (« plafond atteint »): their volume is seeded straight into the journal;
 * - client journals show every status: delivered, bounced (an address with
 *   « bounce@ »), rejected (a mobile ending in 0000), blocked by the cap (an
 *   SMS reminder in the Calanques), and a Lyon client whose SMS reminder was
 *   switched off gets none;
 * - the bell holds read and unread entries of every kind;
 * - Lyon's team shows « Invité » and « Invitation expirée », invited by
 *   Claire Lambert (admin.clinic@kiveto.local);
 * - Paris has an invoice sent once and reminded twice on different days, and
 *   one sent once; Lyon keeps an unpaid invoice with an e-mail and a draft for
 *   the hands-on demo.
 *
 * Nothing is left on the queues for a dev worker: delayed messages (the
 * 2-minute appointment syncs, the simulator's delivery webhooks) are made due
 * and consumed in-process, and the « Nouveau RDV » notices of the bulk
 * fixture bookings — they were never booked by a colleague — are dropped.
 */
final class NotificationDemoStory extends Story
{
    private const string PARIS     = ClinicDataStory::INDEPENDENT_CLINIC_ID;
    private const string LYON      = ClinicDataStory::GROUP_CLINIC_ID;
    private const string CALANQUES = ClinicDataStory::CALM_CLINIC_ID;

    /** The Calanques overage cap, small enough for the seeded volume to reach it. */
    private const int CALANQUES_CAP = 50;

    private \DateTimeZone $paris;

    /** @var array<string, string> client id => their animal */
    private array $animals = [];

    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
        private readonly Connection $connection,
        #[Autowire(service: 'messenger.routable_message_bus')]
        private readonly MessageBusInterface $routableBus,
        #[Autowire(service: 'messenger.transport.async')]
        private readonly ReceiverInterface $async,
        #[Autowire(service: 'messenger.transport.async_external_notification')]
        private readonly ReceiverInterface $external,
        // Like messenger:consume: services are reset after each message, so
        // that the dev profiler's query log does not outgrow the memory limit.
        #[Autowire(service: 'services_resetter')]
        private readonly ServicesResetterInterface $servicesResetter,
    ) {
        $this->paris = new \DateTimeZone('Europe/Paris');
    }

    public function build(): void
    {
        // The bulk fixture bookings were not made by a colleague: no bell for them.
        $this->connection->executeStatement(
            "DELETE FROM shared__messenger_messages WHERE delivered_at IS NULL AND (body LIKE '%PostNewAppointmentNotice%' OR body LIKE '%WithdrawNewAppointmentNotice%')",
        );

        $this->commandBus->dispatch(new SyncAllClinicMessagingProfiles());
        $this->commandBus->dispatch(new UpdateClinicMessagingSettings(self::CALANQUES, 'Calanques', self::CALANQUES_CAP));

        // The confirmations of the fixture appointments, stock alerts, and the
        // integration events the stories above published.
        $this->drain();

        $this->seedSmsVolume(self::LYON, 85);
        $this->seedSmsVolume(self::CALANQUES, 118);

        $admin = $this->userId('admin.clinic@kiveto.local');
        $eric  = $this->userId('vet@kiveto.local');
        $sarah = $this->userId('vet.lyon@kiveto.local');

        $reminded = $this->appointments($admin, $eric, $sarah);
        $this->invitations($admin);
        $this->invoices();
        $this->commandBus->dispatch(new NotifyExpiringLots());
        $this->drain();

        // The SMS reminders, due now rather than tomorrow.
        $this->makeRemindersDue($reminded);
        $this->commandBus->dispatch(new RequestDueReminders());
        $this->drain();

        $this->readSomeBellEntries();
    }

    /**
     * Bookings by the Lyon administrator for the veterinarians (« Nouveau
     * RDV »), each with an owner that shows one delivery outcome.
     *
     * @return list<string> the appointments whose SMS reminder is made due
     */
    private function appointments(string $admin, string $eric, string $sarah): array
    {
        // E-mail confirmation that bounces.
        $camille = $this->client(self::LYON, 'madam', 'Camille', 'Roche', email: 'camille.bounce@exemple.fr', mobile: null, reminderChannels: [], animal: ['Moka', 'cat', 'Européen']);
        $this->book(self::LYON, $sarah, $camille, 3, 'Vaccination', $admin);

        // SMS confirmation rejected by the operator (a number ending in 0000).
        $julien = $this->client(self::LYON, 'mister', 'Julien', 'Marchal', email: null, mobile: '+33612340000', reminderChannels: ['sms'], animal: ['Rex', 'dog', 'Berger Allemand'], preferred: 'sms');
        $this->book(self::LYON, $eric, $julien, 4, 'Boiterie postérieur gauche', $admin);

        // Confirmed by e-mail; she then asked for no SMS reminder.
        $lea      = $this->client(self::LYON, 'madam', 'Léa', 'Bonnet', email: 'lea.bonnet@exemple.fr', mobile: '+33678451290', reminderChannels: ['sms'], animal: ['Caramel', 'nac', null]);
        $leaVisit = $this->book(self::LYON, $eric, $lea, 2, 'Contrôle dentaire', $admin);

        // Confirmed by e-mail, reminded by SMS: both delivered.
        $nathalie      = $this->client(self::PARIS, 'madam', 'Nathalie', 'Perrin', email: 'nathalie.perrin@exemple.fr', mobile: '+33645127788', reminderChannels: ['sms'], animal: ['Gribouille', 'cat', 'Maine Coon']);
        $nathalieVisit = $this->book(self::PARIS, $eric, $nathalie, 3, 'Rappel de vaccin', null);

        // Calanques over its cap: the confirmation (critical) still goes out,
        // the reminder (non-critical, no e-mail to fall back on) is blocked.
        $marius      = $this->client(self::CALANQUES, 'mister', 'Marius', 'Fabre', email: null, mobile: '+33698123456', reminderChannels: ['sms'], animal: ['Pastis', 'dog', 'Épagneul Breton'], preferred: 'sms');
        $mariusVisit = $this->book(self::CALANQUES, $eric, $marius, 3, 'Vaccination', null);

        $this->drain();

        $this->commandBus->dispatch(new UpdateClientCommunicationPreferences(self::LYON, $lea, null, []));

        return [$leaVisit, $nathalieVisit, $mariusVisit];
    }

    /**
     * One pending and one expired invitation in Lyon, sent by its administrator.
     */
    private function invitations(string $admin): void
    {
        $settings = $this->queryBus->ask(new GetClinicSettings(self::LYON));
        \assert($settings instanceof ClinicSettingsView);
        $inviter = $this->queryBus->ask(new GetStaffPrintedName(self::LYON, $admin));
        \assert(\is_string($inviter));

        $this->invite($settings, $inviter, 'hugo.martin@kiveto.local', 'Hugo', 'Martin', ClinicMemberRole::VETERINARY, '#5b8def');
        $ines = $this->invite($settings, $inviter, 'ines.garcia@kiveto.local', 'Inès', 'Garcia', ClinicMemberRole::VETERINARY_ASSISTANT, '#e0a458');

        // Sent four days ago: the 72-hour link has run out.
        $this->connection->executeStatement(
            "UPDATE identity_access__account_tokens SET created_at = :sent, expires_at = :expired WHERE user_id = :user AND purpose = 'INVITATION'",
            [
                'sent'    => $this->utc('-4 days'),
                'expired' => $this->utc('-1 day'),
                'user'    => Uuid::fromString($ines)->toBinary(),
            ],
        );
    }

    private function invite(ClinicSettingsView $settings, string $inviter, string $email, string $firstName, string $lastName, ClinicMemberRole $role, string $color): string
    {
        $registration = $this->commandBus->dispatch(new RegisterInvitedUser($email));
        \assert($registration instanceof InvitedUserRegistration);

        $this->commandBus->dispatch(new OnboardStaffMember(
            clinicId: self::LYON,
            userId: $registration->userId,
            role: $role,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            firstName: $firstName,
            lastName: $lastName,
            displayName: $firstName . ' ' . $lastName,
            agendaColor: $color,
            sortOrder: 5,
            isVisibleInAgenda: true,
            registrationNumber: ClinicMemberRole::VETERINARY === $role ? 'FR-13579' : null,
            professionalTitle: ClinicMemberRole::VETERINARY === $role ? ProfessionalTitle::DR : null,
        ));

        $this->commandBus->dispatch(new SendInvitation(
            userId: $registration->userId,
            clinicId: self::LYON,
            inviterPrintedName: $inviter,
            clinicName: $settings->name,
            clinicCity: $settings->letterhead['city'] ?? '',
            inviteeFirstName: $firstName,
            roleLabel: TeamPageBuilder::ROLES[$role->value]['l'],
        ));

        return $registration->userId;
    }

    /**
     * Paris: one invoice sent ten days ago then reminded six and two days ago
     * (e-mail and SMS), one sent once yesterday. Lyon: a paid invoice sent
     * once, an unpaid one with an e-mail address left for the demo, and a
     * draft.
     */
    private function invoices(): void
    {
        $reminded = $this->unpaidInvoice(self::PARIS, billingConsent: true);
        $sentOnce = $this->unpaidInvoice(self::PARIS, billingConsent: false, except: $reminded);

        $this->send($reminded, '-10 days 10:12');
        $this->remind($reminded, '-6 days 09:05');
        $this->remind($reminded, '-2 days 09:03');
        $this->send($sentOnce, '-1 day 17:40');

        $lyonPaid = $this->connection->fetchOne(
            "SELECT BIN_TO_UUID(i.id) FROM billing__invoices i
             WHERE i.clinic_id = :clinic AND i.status = 'PAID'
               AND EXISTS (SELECT 1 FROM client__contact_methods cm WHERE cm.client_id = i.payer_client_id AND cm.type = 'email')
             ORDER BY i.issued_at DESC LIMIT 1",
            ['clinic' => Uuid::fromString(self::LYON)->toBinary()],
        );
        \assert(\is_string($lyonPaid));
        $this->send(['clinicId' => self::LYON, 'invoiceId' => $lyonPaid], '-3 days 11:30');

        // Lyon's only unpaid invoice belongs to an archived client: the visit
        // whose consultation is closed is invoiced and left unpaid, its payer
        // given an e-mail address.
        $unpaid = $this->prepareLyonInvoice("c.status = 'CLOSED'");
        $this->commandBus->dispatch(new IssueInvoice(self::LYON, $unpaid));
        $this->giveLyonPayerAnEmail($unpaid);

        // A visit still in progress, its invoice drafted at the desk.
        $draft = $this->prepareLyonInvoice('c.id IS NULL');
        $this->commandBus->dispatch(new AddManualInvoiceLine(self::LYON, $draft, 'Consultation de suivi', '1', 4500, 'veterinary.act.consultation'));
    }

    /** Drafts the invoice of the first Lyon visit not invoiced yet whose consultation matches $condition. */
    private function prepareLyonInvoice(string $condition): string
    {
        $admission = $this->connection->fetchOne(
            'SELECT BIN_TO_UUID(a.id) FROM admission__admissions a
             LEFT JOIN consultation__consultations c ON c.admission_id = a.id
             WHERE a.clinic_id = :clinic AND a.patient_id IS NOT NULL AND ' . $condition . '
               AND NOT EXISTS (SELECT 1 FROM billing__invoices i WHERE i.admission_id = a.id)
             ORDER BY a.opened_at LIMIT 1',
            ['clinic' => Uuid::fromString(self::LYON)->toBinary()],
        );
        \assert(\is_string($admission), 'A Lyon visit left to invoice is expected.');

        $invoiceId = $this->commandBus->dispatch(new PrepareInvoiceForAdmission(self::LYON, $admission));
        \assert(\is_string($invoiceId));

        return $invoiceId;
    }

    /**
     * @param array{clinicId: string, invoiceId: string}|null $except
     *
     * @return array{clinicId: string, invoiceId: string}
     */
    private function unpaidInvoice(string $clinicId, bool $billingConsent, ?array $except = null): array
    {
        $invoiceId = $this->connection->fetchOne(
            "SELECT BIN_TO_UUID(i.id) FROM billing__invoices i JOIN client__clients c ON c.id = i.payer_client_id
             WHERE i.clinic_id = :clinic AND i.status = 'ISSUED' AND i.id <> :except
               AND EXISTS (SELECT 1 FROM client__contact_methods cm WHERE cm.client_id = c.id AND cm.type = 'email')
               AND COALESCE(c.communication_preferences LIKE '%\"billing\"%', 0) = :consent
             ORDER BY i.issued_at LIMIT 1",
            [
                'clinic'  => Uuid::fromString($clinicId)->toBinary(),
                'except'  => Uuid::fromString($except['invoiceId'] ?? '00000000-0000-0000-0000-000000000000')->toBinary(),
                'consent' => (int) $billingConsent,
            ],
        );
        \assert(\is_string($invoiceId), 'An unpaid invoice with an e-mail is expected.');

        return ['clinicId' => $clinicId, 'invoiceId' => $invoiceId];
    }

    /**
     * @param array{clinicId: string, invoiceId: string} $invoice
     */
    private function remind(array $invoice, string $when): void
    {
        $this->commandBus->dispatch(new RemindUnpaidInvoices($invoice['clinicId'], [$invoice['invoiceId']]));
        $this->drain();
        $this->backdateDispatch($invoice['invoiceId'], 'REMINDER', $when);
    }

    /**
     * @param array{clinicId: string, invoiceId: string} $invoice
     */
    private function send(array $invoice, string $when): void
    {
        $this->commandBus->dispatch(new SendInvoiceToClient($invoice['clinicId'], [$invoice['invoiceId']]));
        $this->drain();
        $this->backdateDispatch($invoice['invoiceId'], 'SEND', $when);
    }

    /**
     * Moves the latest dispatch of an invoice — and the messages and journal
     * entries it produced — to another day, so that the history reads « relancée
     * 2 fois » on different days and today's reminder stays possible.
     */
    private function backdateDispatch(string $invoiceId, string $kind, string $when): void
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, notification_ids FROM billing__invoice_dispatches WHERE invoice_id = :invoice AND kind = :kind ORDER BY created_at DESC LIMIT 1',
            ['invoice' => Uuid::fromString($invoiceId)->toBinary(), 'kind' => $kind],
        );
        \assert(\is_array($row));

        $at = $this->utc($when);
        $this->connection->executeStatement('UPDATE billing__invoice_dispatches SET created_at = :at WHERE id = :id', ['at' => $at, 'id' => $row['id']]);

        $ids = json_decode(RowAccessor::string($row, 'notification_ids'), true, flags: \JSON_THROW_ON_ERROR);
        \assert(\is_array($ids));

        foreach ($ids as $messageId) {
            \assert(\is_string($messageId));
            $binary = Uuid::fromString($messageId)->toBinary();
            $this->connection->executeStatement(
                'UPDATE notification__outbound_messages SET created_at = :at, sent_at = IF(sent_at IS NULL, NULL, :at), status_changed_at = :at WHERE id = :id',
                ['at' => $at, 'id' => $binary],
            );
            $this->connection->executeStatement(
                'UPDATE client__client_communications SET occurred_at = :at, status_changed_at = :at WHERE notification_message_id = :id',
                ['at' => $at, 'id' => $binary],
            );
        }
    }

    /** The invoice can then be sent and reminded by e-mail. */
    private function giveLyonPayerAnEmail(string $invoiceId): void
    {
        $clientId = $this->connection->fetchOne(
            'SELECT BIN_TO_UUID(payer_client_id) FROM billing__invoices WHERE id = :invoice',
            ['invoice' => Uuid::fromString($invoiceId)->toBinary()],
        );
        \assert(\is_string($clientId), 'The Lyon invoice is expected to have a payer.');

        $rows = $this->connection->fetchAllAssociative(
            'SELECT type, label, value, is_primary FROM client__contact_methods WHERE client_id = :client ORDER BY is_primary DESC',
            ['client' => Uuid::fromString($clientId)->toBinary()],
        );
        $methods = array_map(static fn (array $row): ReplacementContactMethod => new ReplacementContactMethod(
            RowAccessor::string($row, 'type'),
            RowAccessor::string($row, 'label'),
            RowAccessor::string($row, 'value'),
            (bool) $row['is_primary'],
        ), $rows);

        if ([] !== array_filter($methods, static fn (ReplacementContactMethod $method): bool => 'email' === $method->type)) {
            return;
        }

        $name = $this->connection->fetchAssociative('SELECT first_name, last_name FROM client__clients WHERE id = :client', ['client' => Uuid::fromString($clientId)->toBinary()]);
        \assert(\is_array($name));
        $methods[] = new ReplacementContactMethod('email', 'other', self::slug(RowAccessor::string($name, 'first_name')) . '.' . self::slug(RowAccessor::string($name, 'last_name')) . '@exemple.fr');

        $this->commandBus->dispatch(new ReplaceClientContactMethods(self::LYON, $clientId, $methods));
    }

    /**
     * Fills the clinic's journal with delivered SMS of this month (Europe/Paris)
     * up to the given share of its quota.
     */
    private function seedSmsVolume(string $clinicId, int $percent): void
    {
        $usage = $this->queryBus->ask(new GetSmsUsage($clinicId));
        \assert($usage instanceof SmsUsageView);

        $missing = (int) ceil($usage->quota * $percent / 100) - $usage->used;

        if ($missing <= 0) {
            return;
        }

        $sender = $this->connection->fetchOne('SELECT sms_sender FROM notification__clinic_messaging_profiles WHERE clinic_id = :clinic', ['clinic' => Uuid::fromString($clinicId)->toBinary()]);
        $sender = \is_string($sender) && '' !== $sender ? $sender : 'Kiveto';

        $now        = new \DateTimeImmutable('now', $this->paris);
        $monthStart = $now->modify('first day of this month')->setTime(8, 0);
        $span       = max(60, $now->getTimestamp() - 3600 - $monthStart->getTimestamp());
        $parts      = [];

        // One message in five is a long one (two segments).
        for ($remaining = $missing, $i = 0; $remaining > 0; ++$i) {
            $segments = 0 === $i % 5 && $remaining >= 2 ? 2 : 1;
            $parts[]  = $segments;
            $remaining -= $segments;
        }

        $count = \count($parts);
        \Zenstruck\Foundry\Persistence\flush_after(function () use ($parts, $count, $clinicId, $sender, $monthStart, $span): void {
            foreach ($parts as $i => $segments) {
                $sentAt = $monthStart->setTimestamp($monthStart->getTimestamp() + intdiv($span * $i, $count));
                OutboundMessageEntityFactory::new()->deliveredSms($clinicId, $sender, $sentAt, $segments)->create();
            }
        });
    }

    /**
     * @param list<string> $appointmentIds
     */
    private function makeRemindersDue(array $appointmentIds): void
    {
        foreach ($appointmentIds as $appointmentId) {
            $this->connection->executeStatement(
                'UPDATE scheduling__appointment_notifications SET reminder_due_at_utc = :due WHERE appointment_id = :id AND reminder_due_at_utc IS NOT NULL',
                ['due' => $this->utc('-1 minute'), 'id' => Uuid::fromString($appointmentId)->toBinary()],
            );
        }
    }

    /**
     * Each user's older entries of a kind are read, the newest stays unread.
     */
    private function readSomeBellEntries(): void
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT BIN_TO_UUID(n.id) AS id, BIN_TO_UUID(n.user_id) AS user_id FROM notification__in_app_notifications n
             WHERE n.read_at IS NULL AND EXISTS (
                 SELECT 1 FROM notification__in_app_notifications newer
                 WHERE newer.user_id = n.user_id AND newer.clinic_id = n.clinic_id AND newer.kind = n.kind
                   AND (newer.created_at > n.created_at OR (newer.created_at = n.created_at AND newer.id > n.id))
             )',
        );

        // A kind posted once per user (« Péremption proche ») still shows a read
        // entry: the oldest one of the kind.
        $rows = [...$rows, ...$this->connection->fetchAllAssociative(
            'SELECT BIN_TO_UUID(n.id) AS id, BIN_TO_UUID(n.user_id) AS user_id FROM notification__in_app_notifications n
             WHERE n.id = (SELECT o.id FROM notification__in_app_notifications o WHERE o.kind = n.kind ORDER BY o.created_at, o.id LIMIT 1)
               AND (SELECT COUNT(*) FROM notification__in_app_notifications a WHERE a.kind = n.kind) > 1
               AND NOT EXISTS (
                 SELECT 1 FROM notification__in_app_notifications x JOIN notification__in_app_notifications y
                   ON y.user_id = x.user_id AND y.clinic_id = x.clinic_id AND y.kind = x.kind AND y.id <> x.id
                 WHERE x.kind = n.kind
             )',
        )];

        foreach ($rows as $row) {
            $this->commandBus->dispatch(new MarkInAppNotificationRead(RowAccessor::string($row, 'id'), RowAccessor::string($row, 'user_id')));
        }
    }

    /**
     * @param list<string>                            $reminderChannels channels of the « Rappels de rendez-vous » consent
     * @param array{0: string, 1: string, 2: ?string} $animal           name, species, breed
     */
    private function client(string $clinicId, string $civility, string $firstName, string $lastName, ?string $email, ?string $mobile, array $reminderChannels, array $animal, ?string $preferred = null): string
    {
        $contacts = [];

        if (null !== $mobile) {
            $contacts[] = new ContactMethodDto(type: 'phone', label: 'mobile', value: $mobile, isPrimary: true);
        }

        if (null !== $email) {
            $contacts[] = new ContactMethodDto(type: 'email', label: 'other', value: $email, isPrimary: true);
        }

        $clientId = $this->commandBus->dispatch(new CreateClient(
            clinicId: $clinicId,
            firstName: $firstName,
            lastName: $lastName,
            contactMethods: $contacts,
            civility: $civility,
            preferredChannel: $preferred,
            channelsByConsentKind: [] !== $reminderChannels ? ['appointment_reminders' => $reminderChannels] : [],
        ));
        \assert(\is_string($clientId));

        $animalId = $this->commandBus->dispatch(new CreateAnimal(
            clinicId: $clinicId,
            name: $animal[0],
            species: $animal[1],
            sex: 'unknown',
            reproductiveStatus: 'unknown',
            isMixedBreed: null === $animal[2],
            breedName: $animal[2],
            birthDate: '2021-04-12',
            color: null,
            photoUrl: null,
            microchipNumber: null,
            tattooNumber: null,
            passportNumber: null,
            registryType: 'none',
            registryNumber: null,
            registryReference: null,
            lifeStatus: 'alive',
            deceasedAt: null,
            missingSince: null,
            transferStatus: 'none',
            soldAt: null,
            givenAt: null,
            auxiliaryContactFirstName: null,
            auxiliaryContactLastName: null,
            auxiliaryContactPhoneNumber: null,
            primaryOwnerClientId: $clientId,
            secondaryOwnerClientIds: [],
        ));
        \assert(\is_string($animalId));

        $this->animals[$clientId] = $animalId;

        return $clientId;
    }

    /**
     * Books the first free evening slot of the day, $inDays days from now.
     */
    private function book(string $clinicId, string $practitioner, string $clientId, int $inDays, string $reason, ?string $actor): string
    {
        $day = new \DateTimeImmutable('today', $this->paris)->modify(\sprintf('+%d days', $inDays));

        foreach (['17:30', '18:00', '18:30', '19:00', '16:30', '16:00'] as $time) {
            [$hour, $minute] = array_map('intval', explode(':', $time));

            try {
                $appointmentId = $this->commandBus->dispatch(new ScheduleAppointment(
                    clinicId: $clinicId,
                    practitionerUserId: $practitioner,
                    startsAtUtc: $day->setTime($hour, $minute)->setTimezone(new \DateTimeZone('UTC')),
                    durationMinutes: 20,
                    reason: $reason,
                    ownerId: $clientId,
                    animalId: $this->animals[$clientId],
                    actorUserId: $actor,
                ));
                \assert(\is_string($appointmentId));

                return $appointmentId;
            } catch (\DomainException) {
                // Taken or closed: the next slot.
            }
        }

        throw new \RuntimeException(\sprintf('No free slot for the demo appointment of %s.', $clientId));
    }

    /**
     * Consumes everything the notification pipeline queued, delayed messages
     * included (the 2-minute appointment syncs, the simulator's webhooks),
     * until both queues are empty.
     */
    private function drain(): void
    {
        for ($round = 0; $round < 10; ++$round) {
            $pending = $this->connection->fetchOne("SELECT COUNT(*) FROM shared__messenger_messages WHERE delivered_at IS NULL AND queue_name IN ('default', 'external_notification')");

            if (0 === RowAccessor::int(['n' => $pending], 'n')) {
                return;
            }

            $this->connection->executeStatement(
                "UPDATE shared__messenger_messages SET available_at = :now WHERE delivered_at IS NULL AND queue_name IN ('default', 'external_notification')",
                ['now' => $this->utc('now')],
            );

            $this->consume($this->external);
            $this->consume($this->async);
        }
    }

    private function consume(ReceiverInterface $receiver): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new ResetServicesListener($this->servicesResetter));
        $dispatcher->addListener(WorkerRunningEvent::class, static function (WorkerRunningEvent $event): void {
            if ($event->isWorkerIdle()) {
                $event->getWorker()->stop();
            }
        });

        new Worker([$receiver], $this->routableBus, $dispatcher)->run(['sleep' => 0]);
    }

    private function userId(string $email): string
    {
        $user = ClinicUserFactory::repository()->findOneBy(['email' => $email]);
        \assert(null !== $user, $email . ' is expected.');

        return $user->getId()->toRfc4122();
    }

    /** A Paris wall-clock moment as stored (UTC, Y-m-d H:i:s). */
    private function utc(string $when): string
    {
        return new \DateTimeImmutable($when, $this->paris)->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private static function slug(string $name): string
    {
        return strtolower((string) preg_replace('/[^a-z]/i', '', (string) iconv('UTF-8', 'ASCII//TRANSLIT', $name)));
    }
}
