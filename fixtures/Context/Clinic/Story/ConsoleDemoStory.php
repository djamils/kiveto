<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Clinic\Story;

use App\Context\Clinic\Application\Command\Account\OpenClinicAccount\OpenClinicAccount;
use App\Context\Clinic\Application\Command\Account\SuspendClinic\SuspendClinic;
use App\Context\Clinic\Application\Command\Staff\OnboardStaffMember\OnboardStaffMember;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipEngagement;
use App\Context\Clinic\Infrastructure\Persistence\Doctrine\Embeddable\LetterheadEmbeddable;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\Audit\Application\Command\RecordAuditEntry\RecordAuditEntry;
use App\System\Audit\Domain\ValueObject\ActorKind;
use App\System\Audit\Domain\ValueObject\AuditCategory;
use App\System\IdentityAccess\Application\Command\RegisterInvitedUser\InvitedUserRegistration;
use App\System\IdentityAccess\Application\Command\RegisterInvitedUser\RegisterInvitedUser;
use App\System\IdentityAccess\Application\Command\SendInvitation\SendInvitation;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Story;

/**
 * The clinics as the Kiveto console sees them (B1, C1–C3), one per state:
 *
 * - Paris: pilot, Vetup in parallel, onboarded — « Pilote actif »;
 * - Lyon: paid, onboarded; the Calanques: internal (the Kiveto demo clinic);
 * - Vieux-Port, created four days ago: two invitations waiting, one sent
 *   three days ago (« À traiter · Invitations »);
 * - Alpilles, created nine days ago: its only invitation expired and nobody
 *   ever signed in (« Activation »);
 * - Côte Bleue: suspended for an unpaid invoice, its vet's account kept.
 *
 * Each new clinic has its « clinic.created » entry in the audit log, signed
 * by the console admin (by « Kiveto », a system actor, when the clinics are
 * loaded without the platform dataset). Loaded before the notifications
 * demo, which sends the queued invitation e-mails.
 */
final class ConsoleDemoStory extends Story
{
    public const string VIEUX_PORT_ID = '01960000-0000-7000-8000-000000000201';
    public const string ALPILLES_ID   = '01960000-0000-7000-8000-000000000202';
    public const string COTE_BLEUE_ID = '01960000-0000-7000-8000-000000000203';

    private const string ADMIN_EMAIL = 'admin@kiveto.local';
    private const string ADMIN_NAME  = 'Djamil Soidiki';

    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly Connection $connection,
    ) {
    }

    public function build(): void
    {
        $pilotEnd = (new \DateTimeImmutable('first day of this month'))->modify('+3 months')->modify('last day of this month')->format('Y-m-d');

        $this->account(ClinicDataStory::INDEPENDENT_CLINIC_ID, 'pilot', $pilotEnd, 'vetup', '-40 days', 'Pilote signé au congrès AFVAC. Contact : Dr Lambert.');
        $this->account(ClinicDataStory::GROUP_CLINIC_ID, 'paid', null, 'none', '-120 days', '');
        $this->account(ClinicDataStory::CALM_CLINIC_ID, 'internal', null, null, '-200 days', 'Clinique de démonstration Kiveto.');

        $this->clinic(self::VIEUX_PORT_ID, 'clinic-vieux-port', 'Clinique du Vieux-Port', '-4 days', new LetterheadEmbeddable(
            legalName: 'SELARL Vétérinaires du Vieux-Port',
            legalForm: 'SELARL',
            siret: '90223344500017',
            apeCode: '7500Z',
            streetLine1: '18 quai de Rive-Neuve',
            postalCode: '13007',
            city: 'Marseille',
            countryCode: 'FR',
            phone: '+33491550101',
            email: 'contact@veto-vieux-port.fr',
        ));
        $this->account(self::VIEUX_PORT_ID, 'pilot', $pilotEnd, 'drveto', null, '');
        $this->invite(self::VIEUX_PORT_ID, 'Clinique du Vieux-Port', 'Marseille', 'sophie.arnaud@kiveto.local', 'Sophie', 'Arnaud', ClinicMemberRole::VETERINARY, true, '-3 days');
        $this->invite(self::VIEUX_PORT_ID, 'Clinique du Vieux-Port', 'Marseille', 'paul.roux@kiveto.local', 'Paul', 'Roux', ClinicMemberRole::VETERINARY_ASSISTANT, false, '-2 hours');

        $this->clinic(self::ALPILLES_ID, 'clinic-alpilles', 'Clinique des Alpilles', '-9 days', new LetterheadEmbeddable(
            legalName: 'SCP Vétérinaire des Alpilles',
            legalForm: 'SCP',
            siret: '90334455600011',
            apeCode: '7500Z',
            streetLine1: '4 boulevard Mirabeau',
            postalCode: '13210',
            city: 'Saint-Rémy-de-Provence',
            countryCode: 'FR',
        ));
        $this->account(self::ALPILLES_ID, 'pilot', $pilotEnd, 'vetocom', null, '');
        $this->invite(self::ALPILLES_ID, 'Clinique des Alpilles', 'Saint-Rémy-de-Provence', 'marc.vidal@kiveto.local', 'Marc', 'Vidal', ClinicMemberRole::VETERINARY, true, '-8 days');

        $this->clinic(self::COTE_BLEUE_ID, 'clinic-cote-bleue', 'Clinique de la Côte Bleue', '-75 days', new LetterheadEmbeddable(
            legalName: 'SARL Côte Bleue Vétérinaire',
            legalForm: 'SARL',
            siret: '90445566700014',
            apeCode: '7500Z',
            streetLine1: '2 avenue du Port',
            postalCode: '13960',
            city: 'Sausset-les-Pins',
            countryCode: 'FR',
            phone: '+33442440202',
        ));
        $this->account(self::COTE_BLEUE_ID, 'paid', null, 'epivet', '-60 days', 'Deux relances de paiement restées sans réponse.');
        $this->invite(self::COTE_BLEUE_ID, 'Clinique de la Côte Bleue', 'Sausset-les-Pins', 'nadia.benali@kiveto.local', 'Nadia', 'Benali', ClinicMemberRole::VETERINARY, true, '-74 days');
        $this->connection->executeStatement(
            "UPDATE identity_access__users SET status = 'ACTIVE', last_login_at = :at WHERE email = 'nadia.benali@kiveto.local'",
            ['at' => $this->utc('-12 days')],
        );
        $this->commandBus->dispatch(new SuspendClinic(self::COTE_BLEUE_ID, 'unpaid', 'Facture d’octobre impayée.', $this->adminId(), self::ADMIN_NAME));
        $this->audit(self::COTE_BLEUE_ID, 'Clinique de la Côte Bleue', 'clinic.suspended', 'Clinique suspendue · impayé', 'now', ['Motif' => 'Impayé', 'Commentaire' => 'Facture d’octobre impayée.']);
    }

    private function clinic(string $id, string $slug, string $name, string $created, LetterheadEmbeddable $letterhead): void
    {
        $at = new \DateTimeImmutable($created);

        ClinicEntityFactory::new()
            ->withId($id)
            ->withSlug($slug)
            ->withName($name)
            ->withLetterhead($letterhead)
            ->create(['timeZone' => 'Europe/Paris', 'locale' => 'fr-FR', 'createdAt' => $at, 'updatedAt' => $at])
        ;

        $this->audit($id, $name, 'clinic.created', 'Clinique créée : ' . $name, $created, ['Tenant' => $slug, 'SIRET' => (string) $letterhead->siret]);
    }

    private function account(string $clinicId, string $plan, ?string $pilotEnd, ?string $software, ?string $onboarded, string $note): void
    {
        $this->commandBus->dispatch(new OpenClinicAccount($clinicId, $plan, $pilotEnd, $software));

        $this->connection->executeStatement(
            'UPDATE clinic__clinic_accounts SET internal_note = :note, onboarded_at = :onboarded WHERE clinic_id = :clinic',
            ['note' => $note, 'onboarded' => null !== $onboarded ? $this->utc($onboarded) : null, 'clinic' => Uuid::fromString($clinicId)->toBinary()],
        );
    }

    /**
     * An invitation as the console sends it, dated `$sent` (its link lasts
     * seven days from then).
     */
    private function invite(string $clinicId, string $clinicName, string $city, string $email, string $firstName, string $lastName, ClinicMemberRole $role, bool $administrator, string $sent): void
    {
        $registration = $this->commandBus->dispatch(new RegisterInvitedUser($email));
        \assert($registration instanceof InvitedUserRegistration);

        $this->commandBus->dispatch(new OnboardStaffMember(
            clinicId: $clinicId,
            userId: $registration->userId,
            role: $role,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            firstName: $firstName,
            lastName: $lastName,
            displayName: $firstName . ' ' . $lastName,
            agendaColor: '#64748b',
            sortOrder: 0,
            isVisibleInAgenda: true,
            isClinicAdministrator: $administrator,
        ));
        $this->commandBus->dispatch(new SendInvitation(
            userId: $registration->userId,
            clinicId: $clinicId,
            inviterPrintedName: 'L’équipe Kiveto',
            clinicName: $clinicName,
            clinicCity: $city,
            inviteeFirstName: $firstName,
            roleLabel: ClinicMemberRole::VETERINARY === $role ? 'Vétérinaire' : 'ASV',
        ));

        $user = Uuid::fromString($registration->userId)->toBinary();
        $this->connection->executeStatement('UPDATE identity_access__users SET created_at = :at WHERE id = :user', ['at' => $this->utc($sent), 'user' => $user]);
        $this->connection->executeStatement('UPDATE clinic__clinic_memberships SET created_at_utc = :at, valid_from_utc = :at WHERE user_id = :user', ['at' => $this->utc($sent), 'user' => $user]);
        $this->connection->executeStatement(
            "UPDATE identity_access__account_tokens SET created_at = :at, expires_at = :expires WHERE user_id = :user AND purpose = 'INVITATION'",
            ['at' => $this->utc($sent), 'expires' => $this->utc($sent . ' +7 days'), 'user' => $user],
        );

        $this->audit($clinicId, $clinicName, 'user.invited', \sprintf('Invitation envoyée à %s %s', $firstName, $lastName), $sent, ['E-mail' => $email]);
    }

    /**
     * @param array<string, string> $details
     */
    private function audit(string $clinicId, string $clinicName, string $action, string $summary, string $at, array $details): void
    {
        $adminId = $this->adminId();

        $this->commandBus->dispatch(new RecordAuditEntry(
            actorKind: null !== $adminId ? ActorKind::KIVETO : ActorKind::SYSTEM,
            actorId: $adminId,
            actorName: null !== $adminId ? self::ADMIN_NAME : 'Kiveto',
            action: $action,
            category: str_starts_with($action, 'user.') ? AuditCategory::USERS : AuditCategory::CLINICS,
            summary: $summary,
            clinicId: $clinicId,
            clinicName: $clinicName,
            details: array_map(static fn (string $label, string $value): array => ['label' => $label, 'value' => $value], array_keys($details), array_values($details)),
            occurredAt: new \DateTimeImmutable($at),
        ));
    }

    /** The console admin of the platform dataset, when it is loaded. */
    private function adminId(): ?string
    {
        $id = $this->connection->fetchOne('SELECT BIN_TO_UUID(id) FROM identity_access__users WHERE email = ?', [self::ADMIN_EMAIL]);

        return \is_string($id) ? $id : null;
    }

    private function utc(string $relative): string
    {
        return (new \DateTimeImmutable($relative))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
