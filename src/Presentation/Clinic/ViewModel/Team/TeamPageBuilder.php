<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Team;

use App\Context\Clinic\Application\Port\InvitationStatusReaderInterface;
use App\Context\Clinic\Application\Port\StaffProfileReadItem;
use App\Context\Clinic\Application\Port\StaffProfileReadRepositoryInterface;
use App\Context\Clinic\Application\Query\Clinic\GetClinicSettings\ClinicSettingsView;
use App\Context\Clinic\Application\Query\Clinic\GetClinicSettings\GetClinicSettings;
use App\Context\Clinic\Application\Query\Staff\GetStaffPermissionMatrix\GetStaffPermissionMatrix;
use App\Context\Clinic\Application\Query\Staff\GetStaffPermissionMatrix\StaffPermissionMatrix;
use App\Context\Clinic\Application\Query\Staff\ListAllMemberships\ListAllMemberships;
use App\Context\Clinic\Application\Query\Staff\ListAllMemberships\MembershipCollection;
use App\Context\Clinic\Application\Query\Staff\ListAllMemberships\MembershipListItem;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipStatus;
use App\Presentation\Clinic\ViewModel\Shell\ShellViewBuilder;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Presentation\Twig\PhoneFormatRuntime;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * What the team page (equipe-v-topbar_5) draws: every membership of the clinic
 * with the profile behind it, the clinics of the group each member also works
 * in, the role map, and the URLs the page module posts its changes to.
 *
 * Statuses are read off the membership and the account, never stored:
 *  - `off` — access switched off, or its window closed;
 *  - `inv` — invited and not activated yet (« Invité », « Invitation
 *    expirée » once no link of this clinic is usable), not started yet
 *    (window opens later) or never signed in;
 *  - `tmp` — in force, with an end date;
 *  - `act` — in force, open-ended.
 */
final readonly class TeamPageBuilder
{
    public const string CSRF_ID = 'team';
    public const string NIL     = '00000000-0000-7000-8000-000000000000';

    /** Order, wording and look of each role, as the page shows them. */
    public const array ROLES = [
        'VETERINARY'           => ['l' => 'Vétérinaire', 'cl' => 'veto', 'd' => 'Soigne, prescrit et facture.'],
        'VETERINARY_ASSISTANT' => ['l' => 'ASV', 'cl' => 'asv', 'd' => 'Assiste aux soins : agenda, dossiers médicaux, clients et animaux.'],
        'RECEPTIONIST'         => ['l' => 'Accueil', 'cl' => 'accueil', 'd' => 'Agenda, clients et animaux.'],
        'MANAGER'              => ['l' => 'Gérant', 'cl' => 'gerant', 'd' => 'Mène la clinique au quotidien, avec les accès cliniques d’un vétérinaire.'],
    ];

    public const array ENGAGEMENTS = [
        'PARTNER'     => 'Associé',
        'EMPLOYEE'    => 'Salarié · CDI',
        'FIXED_TERM'  => 'Salarié · CDD',
        'CONTRACTOR'  => 'Collaborateur libéral',
        'REPLACEMENT' => 'Remplaçant',
        'INTERN'      => 'Stagiaire',
    ];

    /** Engagements that end by themselves (the page warns when no end date is set). */
    public const array TEMPORARY = ['FIXED_TERM', 'REPLACEMENT', 'INTERN'];

    /**
     * Permission groups of the matrix: slug → [label, detail, short label].
     */
    private const array PERMISSIONS = [
        'Agenda et clients' => [
            'view_dashboard'      => ['Voir le tableau de bord', null, 'tableau de bord'],
            'manage_appointments' => ['Prendre, déplacer et annuler des RDV', null, 'rendez-vous'],
            'manage_clients'      => ['Gérer les dossiers clients', null, 'clients'],
            'manage_animals'      => ['Gérer les fiches des animaux', null, 'animaux'],
        ],
        'Soins' => [
            'view_medical_record' => ['Consulter les dossiers médicaux', null, 'dossiers médicaux'],
            'create_consultation' => ['Rédiger des consultations', null, 'consultations'],
            'create_prescription' => ['Prescrire des médicaments', 'Le n° d’Ordre du prescripteur figure sur chaque ordonnance', 'prescription'],
        ],
        'Gestion' => [
            'manage_billing' => ['Régler la facturation de la clinique', 'Mentions, numérotation et TVA des factures', 'facturation'],
            'manage_staff'   => ['Gérer l’équipe et les paramètres', 'Inviter, modifier et désactiver les membres', 'équipe et paramètres'],
        ],
    ];

    /** Words that do not tell two clinics apart in their two-letter code. */
    private const array GENERIC_WORDS = ['clinique', 'cabinet', 'centre', 'vétérinaire', 'veterinaire', 'hôpital', 'de', 'des', 'du', 'la', 'le', 'les', 'l', 'd', 'et'];

    public function __construct(
        private QueryBusInterface $queryBus,
        private StaffProfileReadRepositoryInterface $profiles,
        private ShellViewBuilder $shell,
        private Security $security,
        private ClockInterface $clock,
        private UrlGeneratorInterface $urls,
        private CsrfTokenManagerInterface $csrf,
        private PhoneFormatRuntime $phones,
        private InvitationStatusReaderInterface $invitations,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function page(string $clinicId): array
    {
        return [
            'token'       => $this->csrf->getToken(self::CSRF_ID)->getValue(),
            'urls'        => $this->urls(),
            'roles'       => $this->roles(),
            'engagements' => $this->engagements(),
            'permissions' => $this->permissions(),
        ] + $this->data($clinicId);
    }

    /**
     * The team itself — what the page asks again after a change.
     *
     * @return array{clinic: string, today: string, clinics: list<array{id: string, code: string, name: string, color: string}>, members: list<array<string, mixed>>}
     */
    public function data(string $clinicId): array
    {
        $settings = $this->queryBus->ask(new GetClinicSettings($clinicId));
        \assert($settings instanceof ClinicSettingsView);

        $timeZone = new \DateTimeZone($settings->timeZone);
        $now      = $this->clock->now();
        $today    = $now->setTimezone($timeZone);

        $memberships = $this->memberships($clinicId);
        $profiles    = $this->profiles->findByMembershipIds(array_map(
            static fn (MembershipListItem $membership): string => $membership->membershipId,
            $memberships,
        ));

        $clinics   = $this->clinics();
        $clinicsOf = $this->clinicsOfUsers($clinicId, $clinics);
        $viewer    = $this->security->getUser();
        $viewerId  = $viewer instanceof SecurityUser ? $viewer->id() : null;
        $invited   = $this->invitations->statuses($clinicId, array_values(array_unique(array_map(
            static fn (MembershipListItem $membership): string => $membership->userId,
            $memberships,
        ))));

        $members = [];

        foreach ($memberships as $membership) {
            $members[] = $this->member(
                $membership,
                $profiles[$membership->membershipId] ?? null,
                $clinicsOf[$membership->userId] ?? [$clinicId],
                $membership->userId === $viewerId,
                $now,
                $today,
                $invited[$membership->userId] ?? null,
            );
        }

        // Current members first, the clinic's administrators at their head, then
        // by role and name; those not started yet, then those who left, last.
        usort($members, static fn (array $a, array $b): int => $a['tri'] <=> $b['tri']);

        return [
            'clinic'  => $settings->name,
            'today'   => $today->format('Y-m-d'),
            'clinics' => $clinics,
            'members' => $members,
        ];
    }

    /** Two letters that tell the clinic apart: « Vieux-Port » → VP, « Calanques » → CA. */
    public static function code(string $name): string
    {
        $words = preg_split('/[\s\'’\-]+/u', $name, -1, \PREG_SPLIT_NO_EMPTY) ?: [];
        $words = array_values(array_filter(
            $words,
            static fn (string $word): bool => !\in_array(mb_strtolower($word), self::GENERIC_WORDS, true),
        ));

        if ([] === $words) {
            return mb_strtoupper(mb_substr($name, 0, 2));
        }

        if (\count($words) >= 2) {
            return mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1));
        }

        return mb_strtoupper(mb_substr($words[0], 0, 2));
    }

    /**
     * @return list<MembershipListItem>
     */
    private function memberships(string $clinicId): array
    {
        $collection = $this->queryBus->ask(new ListAllMemberships(clinicId: $clinicId));
        \assert($collection instanceof MembershipCollection);

        return $collection->memberships;
    }

    /**
     * The clinics the viewer works in: the member's other clinics are only
     * shown within those, so the page never names a clinic the viewer is a
     * stranger to.
     *
     * @return list<array{id: string, code: string, name: string, color: string}>
     */
    private function clinics(): array
    {
        $clinics = [];

        foreach ($this->shell->build()->clinics as $clinic) {
            $clinics[] = [
                'id'    => $clinic->id,
                'code'  => self::code($clinic->name),
                'name'  => $clinic->name,
                'color' => $clinic->color,
            ];
        }

        return $clinics;
    }

    /**
     * User id → ids of the viewer's clinics where that user holds a membership
     * still in force.
     *
     * @param list<array{id: string, code: string, name: string, color: string}> $clinics
     *
     * @return array<string, list<string>>
     */
    private function clinicsOfUsers(string $clinicId, array $clinics): array
    {
        $map = [];

        foreach ($clinics as $clinic) {
            foreach ($this->memberships($clinic['id']) as $membership) {
                $isCurrent = $clinic['id'] === $clinicId;

                if (!$isCurrent && ClinicMembershipStatus::DISABLED === $membership->status) {
                    continue;
                }

                $map[$membership->userId][] = $clinic['id'];
            }
        }

        return $map;
    }

    /**
     * @param list<string> $clinicIds
     *
     * @return array<string, mixed>
     */
    private function member(
        MembershipListItem $membership,
        ?StaffProfileReadItem $profile,
        array $clinicIds,
        bool $isViewer,
        \DateTimeImmutable $now,
        \DateTimeImmutable $today,
        ?string $invitation,
    ): array {
        $timeZone = $today->getTimezone();
        $from     = $membership->validFrom->setTimezone($timeZone);
        $until    = $membership->validUntil?->setTimezone($timeZone);
        $email    = $membership->userEmail;
        $hasOrder = null !== $profile && null !== $profile->registrationNumber;

        [$firstName, $lastName] = null !== $profile && '' !== $profile->lastName
            ? [$profile->firstName, $profile->lastName]
            : $this->namesFromEmail($email);

        [$status, $label, $state] = $this->status($membership, $now, $from, $until, $invitation);

        return [
            'id'      => $membership->membershipId,
            'profile' => $profile?->profileId,
            'p'       => $firstName,
            'n'       => $lastName,
            'titre'   => $hasOrder ? self::titlePrefix($profile->professionalTitle) : '',
            'title'   => $hasOrder ? ($profile->professionalTitle ?? 'DR') : 'DR',
            'role'    => $membership->role->value,
            'admin'   => $membership->isClinicAdministrator,
            'veto'    => $hasOrder,
            'ordre'   => $hasOrder ? $profile->registrationNumber : '',
            'sig'     => null !== $profile && $profile->hasSignature,
            'c'       => null !== $profile ? $profile->agendaColor : '#64748b',
            'visible' => null !== $profile && $profile->isVisibleInAgenda,
            'mail'    => $email,
            'tel'     => null !== $profile && null !== $profile->phone ? $this->phones->phoneDisplay($profile->phone, false, false) : '',
            'clin'    => array_values(array_unique($clinicIds)),
            'contrat' => $membership->engagement->value,
            'tps'     => $membership->workTimePercent,
            'debut'   => $from->format('Y-m-d'),
            'fin'     => $until?->format('Y-m-d'),
            'statut'  => $status,
            'sl'      => $label,
            'etat'    => $state,
            'last'    => $isViewer ? 'en ligne' : $this->lastSeen($membership->lastLoginAt, $today),
            'moi'     => $isViewer,
            // Current members first, the clinic's administrators at their
            // head, then by role and name; those not started yet, then those
            // who left, last.
            'tri' => \sprintf(
                '%d%d%d %s %s',
                self::rank($status),
                $membership->isClinicAdministrator ? 0 : 1,
                (int) array_search($membership->role->value, array_keys(self::ROLES), true),
                mb_strtolower($lastName),
                mb_strtolower($firstName),
            ),
        ];
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function status(
        MembershipListItem $membership,
        \DateTimeImmutable $now,
        \DateTimeImmutable $from,
        ?\DateTimeImmutable $until,
        ?string $invitation,
    ): array {
        if (ClinicMembershipStatus::DISABLED === $membership->status) {
            return ['off', 'Accès désactivé', 'desactive'];
        }

        if (null !== $membership->validUntil && $membership->validUntil < $now) {
            \assert(null !== $until);

            return ['off', 'Accès terminé le ' . $until->format('d/m/Y'), 'termine'];
        }

        if (InvitationStatusReaderInterface::INVITED === $invitation) {
            return ['inv', 'Invité', 'invite'];
        }

        if (InvitationStatusReaderInterface::EXPIRED === $invitation) {
            return ['inv', 'Invitation expirée', 'expire'];
        }

        if ($membership->validFrom > $now) {
            return ['inv', 'Arrive le ' . $from->format('d/m/Y'), 'avenir'];
        }

        if (null === $membership->lastLoginAt) {
            return ['inv', 'Pas encore connecté', 'jamais'];
        }

        if (null !== $until) {
            return ['tmp', 'Jusqu’au ' . $until->format('d/m/Y'), 'temporaire'];
        }

        return ['act', 'Actif', 'actif'];
    }

    private function lastSeen(?\DateTimeImmutable $lastLoginAt, \DateTimeImmutable $today): string
    {
        if (null === $lastLoginAt) {
            return 'jamais connecté';
        }

        $seen = $lastLoginAt->setTimezone($today->getTimezone());
        $days = (int) $seen->setTime(0, 0)->diff($today->setTime(0, 0))->format('%r%a');

        return match (true) {
            $days <= 0  => 'aujourd’hui, ' . $seen->format('H:i'),
            1 === $days => 'hier, ' . $seen->format('H:i'),
            $days < 7   => 'il y a ' . $days . ' jours',
            default     => $seen->format('d/m/Y'),
        };
    }

    /**
     * A member added without a profile (from the back office) still shows a
     * name: the part of their address before the at sign.
     *
     * @return array{0: string, 1: string}
     */
    private function namesFromEmail(string $email): array
    {
        $local = (string) strstr($email, '@', true);
        $parts = preg_split('/[._-]+/', '' !== $local ? $local : $email, -1, \PREG_SPLIT_NO_EMPTY) ?: [$email];
        $parts = array_map(static fn (string $part): string => mb_convert_case($part, \MB_CASE_TITLE), $parts);

        return [\count($parts) > 1 ? $parts[0] : '', \count($parts) > 1 ? implode(' ', \array_slice($parts, 1)) : $parts[0]];
    }

    private static function titlePrefix(?string $title): string
    {
        return match ($title) {
            'PR'    => 'Pr',
            'NONE'  => '',
            default => 'Dr',
        };
    }

    /** Current members first, then those not started, then the ones whose access ended. */
    private static function rank(string $status): int
    {
        return match ($status) {
            'act', 'tmp' => 0,
            'inv'   => 1,
            default => 2,
        };
    }

    /**
     * @return list<array{v: string, l: string, cl: string, d: string}>
     */
    private function roles(): array
    {
        $roles = [];

        foreach (self::ROLES as $value => $role) {
            $roles[] = ['v' => $value] + $role;
        }

        return $roles;
    }

    /**
     * @return list<array{v: string, l: string, tmp: bool}>
     */
    private function engagements(): array
    {
        $engagements = [];

        foreach (self::ENGAGEMENTS as $value => $label) {
            $engagements[] = ['v' => $value, 'l' => $label, 'tmp' => \in_array($value, self::TEMPORARY, true)];
        }

        return $engagements;
    }

    /**
     * The role map, grouped as the matrix shows it.
     *
     * @return array{groups: list<array{l: string, p: list<array{k: string, l: string, d: string|null, c: string}>}>, roles: array<string, list<string>>, admin: list<string>}
     */
    private function permissions(): array
    {
        $matrix = $this->queryBus->ask(new GetStaffPermissionMatrix());
        \assert($matrix instanceof StaffPermissionMatrix);

        $groups = [];

        foreach (self::PERMISSIONS as $group => $permissions) {
            $rows = [];

            foreach ($permissions as $slug => [$label, $detail, $short]) {
                if (\in_array($slug, $matrix->permissions, true)) {
                    $rows[] = ['k' => $slug, 'l' => $label, 'd' => $detail, 'c' => $short];
                }
            }

            $groups[] = ['l' => $group, 'p' => $rows];
        }

        return ['groups' => $groups, 'roles' => $matrix->roles, 'admin' => $matrix->administrator];
    }

    /**
     * @return array<string, string>
     */
    private function urls(): array
    {
        $member = ['membershipId' => self::NIL];

        return [
            'data'        => $this->urls->generate('clinic_settings_team_data'),
            'page'        => $this->urls->generate('clinic_settings_team'),
            'invite'      => $this->urls->generate('clinic_settings_team_add'),
            'planning'    => $this->urls->generate('clinic_scheduling_planning'),
            'name'        => $this->urls->generate('clinic_settings_team_name', $member),
            'phone'       => $this->urls->generate('clinic_settings_team_phone', $member),
            'agenda'      => $this->urls->generate('clinic_settings_team_agenda', $member),
            'credentials' => $this->urls->generate('clinic_settings_team_credentials', $member),
            'role'        => $this->urls->generate('clinic_settings_team_role', $member),
            'grant'       => $this->urls->generate('clinic_settings_team_administration_grant', $member),
            'revoke'      => $this->urls->generate('clinic_settings_team_administration_revoke', $member),
            'engagement'  => $this->urls->generate('clinic_settings_team_engagement', $member),
            'workTime'    => $this->urls->generate('clinic_settings_team_work_time', $member),
            'validity'    => $this->urls->generate('clinic_settings_team_validity', $member),
            'disable'     => $this->urls->generate('clinic_settings_team_disable', $member),
            'enable'      => $this->urls->generate('clinic_settings_team_enable', $member),
            'resend'      => $this->urls->generate('clinic_settings_team_resend', $member),
        ];
    }
}
