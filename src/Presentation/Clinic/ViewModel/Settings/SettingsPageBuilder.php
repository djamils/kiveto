<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Settings;

use App\Context\Billing\Application\Query\PeekNextDocumentNumbers\NextDocumentNumbers;
use App\Context\Billing\Application\Query\PeekNextDocumentNumbers\PeekNextDocumentNumbers;
use App\Context\Clinic\Application\Query\Clinic\GetClinicSettings\ClinicSettingsView;
use App\Context\Clinic\Application\Query\Clinic\GetClinicSettings\GetClinicSettings;
use App\Context\Clinic\Application\Query\Clinic\ListClinicsForUser\AccessibleClinic;
use App\Context\Clinic\Application\Query\Clinic\ListClinicsForUser\ListClinicsForUser;
use App\Context\Clinic\Application\Query\Staff\GetStaffPrintedName\GetStaffPrintedName;
use App\Context\Clinic\Application\Query\Staff\ListAllMemberships\ListAllMemberships;
use App\Context\Clinic\Application\Query\Staff\ListAllMemberships\MembershipCollection;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipStatus;
use App\Context\Clinic\Domain\ValueObject\OrdinalCouncil;
use App\Context\ClinicListing\Application\Query\GetListingSettings\GetListingSettings;
use App\Context\ClinicListing\Application\Query\GetListingSettings\ListingSettingsView;
use App\Context\Scheduling\Application\Query\GetOnlineBookingSettings\GetOnlineBookingSettings;
use App\Context\Scheduling\Application\Query\GetOnlineBookingSettings\OnlineBookingSettingsView;
use App\Context\Scheduling\Application\Query\GetReminderDelay\GetReminderDelay;
use App\Context\Scheduling\Application\Query\ListBookingMotifs\BookingMotifView;
use App\Context\Scheduling\Application\Query\ListBookingMotifs\ListBookingMotifs;
use App\Presentation\Clinic\Security\ClinicPermissionGuard;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Presentation\Twig\PhoneFormatRuntime;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use App\System\Notification\Application\Query\GetSmsUsage\GetSmsUsage;
use App\System\Notification\Application\Query\GetSmsUsage\SmsUsageView;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Everything the settings page draws, in one payload: the eight sections the
 * clinic can set today (identity, hours, notifications, agenda, online
 * booking, directory, billing, the member's own preferences) with their current values, which of them the member may open,
 * and where each one is saved. The page module renders the sections from it
 * and posts a whole section back on every change.
 */
final readonly class SettingsPageBuilder
{
    public const string CSRF_ID = 'settings';

    /** Section of the page → the route that opens it. */
    public const array ROUTES = [
        'identite'      => 'clinic_settings',
        'horaires'      => 'clinic_settings_hours',
        'notifications' => 'clinic_settings_notifications',
        'agenda'        => 'clinic_settings_agenda',
        // Paramètres › Réservation en ligne (Portal bookings, S4h)
        'reservation-en-ligne' => 'clinic_settings_online_booking',
        // Paramètres › Annuaire (the Portal's clinic directory, S7c)
        'annuaire'    => 'clinic_settings_directory',
        'facturation' => 'clinic_settings_billing',
        'moi'         => 'clinic_settings_preferences',
    ];

    /** Who can take an online booking: the same roles as an appointment's practitioner. */
    private const array PRACTITIONER_ROLES = [ClinicMemberRole::VETERINARY, ClinicMemberRole::VETERINARY_ASSISTANT];

    /** Planning block types that take appointments, as the Planning names them. */
    private const array BLOCK_TYPES = [
        'consultation' => 'Consultation',
        'health_check' => 'Bilan de santé',
        'surgery'      => 'Chirurgie',
        'emergency'    => 'Urgences',
        'on_call'      => 'Garde',
    ];

    private const array PREVENTIVE_CARE_KINDS = [
        'vaccination'       => 'Vaccination',
        'internal_parasite' => 'Vermifuge',
        'external_parasite' => 'Antiparasitaire externe',
        'other'             => 'Autre soin préventif',
    ];

    private const array LOCALES = [
        'fr-FR' => 'Français (France)',
        'en-GB' => 'English (United Kingdom)',
        'en-US' => 'English (United States)',
        'de-DE' => 'Deutsch (Deutschland)',
        'es-ES' => 'Español (España)',
    ];

    private const array ZONES = [
        'Europe/Paris'       => 'Paris (métropole)',
        'Europe/Brussels'    => 'Bruxelles',
        'Europe/Zurich'      => 'Zurich',
        'Europe/Luxembourg'  => 'Luxembourg',
        'Indian/Reunion'     => 'La Réunion',
        'America/Martinique' => 'Martinique',
        'America/Guadeloupe' => 'Guadeloupe',
        'America/Cayenne'    => 'Guyane',
        'Indian/Mayotte'     => 'Mayotte',
        'Pacific/Noumea'     => 'Nouvelle-Calédonie',
        'Pacific/Tahiti'     => 'Polynésie française',
        'UTC'                => 'UTC',
    ];

    private const array MONTHS = [1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

    private const array DAYS = [1 => 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];

    public function __construct(
        private QueryBusInterface $queryBus,
        private ClinicPermissionGuard $guard,
        private Security $security,
        private ClockInterface $clock,
        private UrlGeneratorInterface $urls,
        private CsrfTokenManagerInterface $csrf,
        private PhoneFormatRuntime $phones,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function page(string $clinicId, string $section): array
    {
        $settings = $this->queryBus->ask(new GetClinicSettings($clinicId));
        \assert($settings instanceof ClinicSettingsView);

        $can = [
            'identite'      => $this->guard->has(ClinicPermissionGuard::MANAGE_STAFF),
            'horaires'      => $this->guard->has(ClinicPermissionGuard::MANAGE_STAFF),
            'notifications' => $this->guard->has(ClinicPermissionGuard::MANAGE_STAFF),
            'agenda'        => $this->guard->has(ClinicPermissionGuard::MANAGE_STAFF),
            // Same right as the agenda: the clinic's administrators.
            'reservation-en-ligne' => $this->guard->has(ClinicPermissionGuard::MANAGE_STAFF),
            'annuaire'             => $this->guard->has(ClinicPermissionGuard::MANAGE_STAFF),
            'facturation'          => $this->guard->has(ClinicPermissionGuard::MANAGE_BILLING),
            'moi'                  => true,
        ];

        return [
            'section'              => $section,
            'clinicName'           => $settings->name,
            'token'                => $this->csrf->getToken(self::CSRF_ID)->getValue(),
            'can'                  => $can,
            'urls'                 => $this->urls($settings),
            'identite'             => $can['identite'] ? $this->identity($settings) : null,
            'horaires'             => $can['horaires'] ? $this->hours($settings) : null,
            'notifications'        => $can['notifications'] ? $this->notifications($settings) : null,
            'agenda'               => $can['agenda'] ? $this->agenda($settings) : null,
            'reservation-en-ligne' => $can['reservation-en-ligne'] ? $this->onlineBooking($settings) : null,
            'annuaire'             => $can['annuaire'] ? $this->directory($settings) : null,
            'facturation'          => $can['facturation'] ? $this->billing($settings) : null,
            'moi'                  => $this->preferences(),
            'options'              => [
                'councils' => array_map(static fn (OrdinalCouncil $council): array => [$council->value, $council->label()], OrdinalCouncil::cases()),
                'locales'  => self::pairs(self::LOCALES, $settings->locale),
                'zones'    => self::pairs(self::ZONES, $settings->timeZone),
            ],
        ];
    }

    /**
     * The identity block as the fields show it: the phone in its national form,
     * the SIRET and the VAT number spaced as they are written on paper.
     *
     * @return array<string, string|null>
     */
    public function identity(ClinicSettingsView $settings): array
    {
        $l = $settings->letterhead;

        return [
            'id.nom'      => $settings->name,
            'id.rs'       => $l['legalName'] ?? null,
            'id.forme'    => $l['legalForm'] ?? null,
            'id.capital'  => $l['shareCapital'] ?? null,
            'id.rcsVille' => $l['rcsCity'] ?? null,
            'id.rcsN'     => $l['rcsNumber'] ?? null,
            'id.ape'      => $l['apeCode'] ?? null,
            'id.adr1'     => $l['streetLine1'] ?? null,
            'id.adr2'     => $l['streetLine2'] ?? null,
            'id.cp'       => $l['postalCode'] ?? null,
            'id.ville'    => $l['city'] ?? null,
            'id.pays'     => $l['countryCode'] ?? null,
            'id.tel'      => null !== ($l['phone'] ?? null) ? $this->phones->phoneDisplay($l['phone'], false, false) : null,
            'id.mail'     => $l['email'] ?? null,
            'id.web'      => $l['website'] ?? null,
            'id.siret'    => self::siret($l['siret'] ?? null),
            'id.tva'      => self::vat($l['vatNumber'] ?? null),
            'id.ordreN'   => $l['ordinalNumber'] ?? null,
            'id.cro'      => $l['ordinalCouncil'] ?? null,
            'id.rcpA'     => $l['liabilityInsurer'] ?? null,
            'id.rcpN'     => $l['liabilityPolicyNumber'] ?? null,
            'id.couleur'  => $settings->brandColor,
            'id.locale'   => $settings->locale,
            'id.tz'       => $settings->timeZone,
        ];
    }

    /**
     * The week day by day, Monday first, and the closures folded back into the
     * ranges they were typed as: consecutive days closed for the same reason
     * read as one closure.
     *
     * @return array{week: list<array{0: string, 1: list<array{0: string, 1: string}>}>, closures: list<array{du: string, au: string, motif: string}>}
     */
    public function hours(ClinicSettingsView $settings): array
    {
        $week = [];

        foreach (self::DAYS as $day => $label) {
            $week[] = [$label, array_map(
                static fn (array $slot): array => [$slot['opensAt'], $slot['closesAt']],
                $settings->week[$day] ?? [],
            )];
        }

        $closures = $settings->closures;
        usort($closures, static fn (array $a, array $b): int => strcmp($a['date'], $b['date']));

        $ranges = [];

        foreach ($closures as $closure) {
            $reason = $closure['reason'] ?? '';
            $last   = array_key_last($ranges);

            $follows = null !== $last
                && $ranges[$last]['motif'] === $reason
                && (new \DateTimeImmutable($ranges[$last]['au']))->modify('+1 day')->format('Y-m-d') === $closure['date'];

            if ($follows) {
                $ranges[$last]['au'] = $closure['date'];

                continue;
            }

            $ranges[] = ['du' => $closure['date'], 'au' => $closure['date'], 'motif' => $reason];
        }

        return ['week' => $week, 'closures' => array_map(
            static fn (array $range): array => ['du' => $range['du'], 'au' => $range['au'], 'motif' => $range['motif']],
            $ranges,
        )];
    }

    /**
     * The SMS sender and overage cap as the fields show them, and the month's
     * usage card: billed segments against the quota (Notification's count),
     * the overage and what it comes to.
     *
     * @return array<string, mixed>
     */
    public function notifications(ClinicSettingsView $settings): array
    {
        $usage = $this->queryBus->ask(new GetSmsUsage($settings->id));
        \assert($usage instanceof SmsUsageView);

        [$year, $month] = array_map(\intval(...), explode('-', $usage->month));

        return [
            'no.exp' => $settings->smsSender,
            'no.cap' => $settings->overageCapSegments,
            'usage'  => [
                'month'         => (self::MONTHS[$month] ?? '') . ' ' . $year,
                'used'          => $usage->used,
                'quota'         => $usage->quota,
                'queued'        => $usage->queued,
                'percent'       => $usage->percent,
                'overage'       => $usage->overage,
                'overageAmount' => number_format($usage->overageAmountCents / 100, 2, ',', "\u{202F}") . "\u{00A0}€ HT",
                'cap'           => $usage->cap,
                'capReached'    => $usage->capReached,
            ],
        ];
    }

    /**
     * When the SMS reminder of an appointment is sent (Scheduling's setting).
     *
     * @return array{'ag.rappel': string}
     */
    public function agenda(ClinicSettingsView $settings): array
    {
        $delay = $this->queryBus->ask(new GetReminderDelay($settings->id));
        \assert(\is_string($delay));

        return ['ag.rappel' => $delay];
    }

    /**
     * Online booking from the Portal: the general settings, the clinic's
     * motifs (archived ones left out), who and which planning blocks a motif
     * may be booked with, and whether the opening hours the slots are cut
     * from are missing.
     *
     * @return array{
     *     settings: array<string, bool|int>,
     *     motifs: list<array<string, mixed>>,
     *     practitioners: list<array{0: string, 1: string}>,
     *     blockTypes: list<array{0: string, 1: string}>,
     *     careKinds: list<array{0: string, 1: string}>,
     *     hoursMissing: bool,
     * }
     */
    public function onlineBooking(ClinicSettingsView $settings): array
    {
        $booking = $this->queryBus->ask(new GetOnlineBookingSettings($settings->id));
        \assert($booking instanceof OnlineBookingSettingsView);

        $motifs = $this->queryBus->ask(new ListBookingMotifs($settings->id));
        \assert(\is_array($motifs));

        $hoursMissing = true;

        foreach ($settings->week as $slots) {
            if ([] !== $slots) {
                $hoursMissing = false;

                break;
            }
        }

        return [
            'settings' => [
                'enabled'                 => $booking->enabled,
                'gridStepMinutes'         => $booking->gridStepMinutes,
                'minLeadTimeMinutes'      => $booking->minLeadTimeMinutes,
                'minLeadTimeManualHours'  => $booking->minLeadTimeManualHours,
                'horizonDays'             => $booking->horizonDays,
                'cancellationWindowHours' => $booking->cancellationWindowHours,
                'newClientBookingEnabled' => $booking->newClientBookingEnabled,
            ],
            'motifs' => array_values(array_map(static function (mixed $motif): array {
                \assert($motif instanceof BookingMotifView);

                return [
                    'id'                      => $motif->id,
                    'label'                   => $motif->label,
                    'description'             => $motif->description,
                    'durationMin'             => $motif->durationMin,
                    'onlineEnabled'           => $motif->onlineEnabled,
                    'confirmationKnownClient' => $motif->confirmationKnownClient,
                    'allowedBlockTypes'       => $motif->allowedBlockTypes,
                    'practitionerIds'         => $motif->practitionerIds,
                    'preventiveCareKind'      => $motif->preventiveCareKind,
                    'newClientsAllowed'       => $motif->newClientsAllowed,
                    'sortOrder'               => $motif->sortOrder,
                ];
            }, $motifs)),
            'practitioners' => $this->bookablePractitioners($settings->id),
            'blockTypes'    => self::pairs(self::BLOCK_TYPES, null),
            'careKinds'     => self::pairs(self::PREVENTIVE_CARE_KINDS, null),
            'hoursMissing'  => $hoursMissing,
        ];
    }

    /**
     * The clinic in the Portal's directory: whether it is listed, takes new
     * clients, the tags on its card, and whether the letterhead address it is
     * shown and placed on the map with is missing.
     *
     * @return array{listed: bool, acceptsNewClients: bool, tags: list<string>, addressMissing: bool}
     */
    public function directory(ClinicSettingsView $settings): array
    {
        $listing = $this->queryBus->ask(new GetListingSettings($settings->id));
        \assert($listing instanceof ListingSettingsView);

        return [
            'listed'            => $listing->listed,
            'acceptsNewClients' => $listing->acceptsNewClients,
            'tags'              => $listing->tags,
            'addressMissing'    => null === ($settings->letterhead['streetLine1'] ?? null) || null === ($settings->letterhead['city'] ?? null),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function billing(ClinicSettingsView $settings): array
    {
        $b    = $settings->billing;
        $year = (int) $this->clock->now()->setTimezone(new \DateTimeZone($settings->timeZone))->format('Y');

        $next = $this->queryBus->ask(new PeekNextDocumentNumbers($settings->id, $year));
        \assert($next instanceof NextDocumentNumbers);

        $siret = $settings->letterhead['siret'] ?? null;
        $terms = $b['paymentTermsDays'] ?? null;
        $pro   = $b['professionalPaymentTermsDays'] ?? null;

        return [
            'fa.prefixe'  => $b['invoiceNumberPrefix'] ?? null,
            'fa.av'       => $b['creditNotePrefix'] ?? null,
            'fa.cond'     => \is_int($terms) ? (string) $terms : null,
            'fa.pro'      => \is_int($pro) ? $pro : null,
            'fe.regime'   => $b['vatRegime'] ?? null,
            'fe.debits'   => true === ($b['vatOnDebits'] ?? false),
            'fa.moyens'   => \is_array($b['acceptedPaymentMethods'] ?? null) ? $b['acceptedPaymentMethods'] : ['CARD', 'CASH', 'CHECK', 'TRANSFER'],
            'fa.iban'     => self::iban(\is_string($b['iban'] ?? null) ? $b['iban'] : null),
            'fa.bic'      => $b['bic'] ?? null,
            'fa.mentions' => $b['invoiceFooterNote'] ?? null,
            'fa.adr1'     => $b['streetLine1'] ?? null,
            'fa.adr2'     => $b['streetLine2'] ?? null,
            'fa.cp'       => $b['postalCode'] ?? null,
            'fa.ville'    => $b['city'] ?? null,
            'nextInvoice' => $next->invoice,
            'nextCredit'  => $next->creditNote,
            'siren'       => \is_string($siret) && 14 === mb_strlen($siret) ? mb_substr($siret, 0, 3) . ' ' . mb_substr($siret, 3, 3) . ' ' . mb_substr($siret, 6, 3) : null,
        ];
    }

    /**
     * The member's own settings: the clinic opened at sign-in, among those they
     * belong to (with the colour each chose, which the colour picker warns
     * about).
     *
     * @return array{'moi.clin': string|null, clinics: list<array{0: string, 1: string, 2: string|null}>}
     */
    public function preferences(): array
    {
        $user = $this->security->getUser();

        if (!$user instanceof SecurityUser) {
            return ['moi.clin' => null, 'clinics' => []];
        }

        $accessible = $this->queryBus->ask(new ListClinicsForUser($user->id()));
        \assert(\is_array($accessible));

        $default = null;
        $clinics = [];

        foreach ($accessible as $clinic) {
            \assert($clinic instanceof AccessibleClinic);

            $clinics[] = [$clinic->clinicId, $clinic->clinicName, $clinic->brandColor];

            if ($clinic->isDefault) {
                $default = $clinic->clinicId;
            }
        }

        return ['moi.clin' => $default, 'clinics' => $clinics];
    }

    /**
     * The members a motif can be booked with: active memberships in force
     * today as veterinarian or assistant (Clinic's membership list, as the
     * appointment's eligibility check reads it), by printed name.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function bookablePractitioners(string $clinicId): array
    {
        $collection = $this->queryBus->ask(new ListAllMemberships(clinicId: $clinicId, status: ClinicMembershipStatus::ACTIVE));
        \assert($collection instanceof MembershipCollection);

        $now           = $this->clock->now();
        $practitioners = [];

        foreach ($collection->memberships as $membership) {
            $inForce = $membership->validFrom <= $now && (null === $membership->validUntil || $membership->validUntil >= $now);

            if (!$inForce || !\in_array($membership->role, self::PRACTITIONER_ROLES, true)) {
                continue;
            }

            $name = $this->queryBus->ask(new GetStaffPrintedName($clinicId, $membership->userId));

            $practitioners[$membership->userId] = [$membership->userId, \is_string($name) && '' !== $name ? $name : $membership->userEmail];
        }

        $practitioners = array_values($practitioners);
        usort($practitioners, static fn (array $a, array $b): int => strnatcasecmp($a[1], $b[1]));

        return $practitioners;
    }

    /**
     * @return array<string, mixed>
     */
    private function urls(ClinicSettingsView $settings): array
    {
        $go = fn (string $route): string => $this->urls->generate($route);

        return [
            'sections' => array_map($go, self::ROUTES),
            'save'     => [
                'identite'             => $go('clinic_settings_identity_save'),
                'horaires'             => $go('clinic_settings_hours_save'),
                'notifications'        => $go('clinic_settings_notifications_save'),
                'agenda'               => $go('clinic_settings_agenda_save'),
                'reservation-en-ligne' => $go('clinic_settings_online_booking_save'),
                'annuaire'             => $go('clinic_settings_directory_save'),
                'facturation'          => $go('clinic_settings_billing_save'),
                'moi'                  => $go('clinic_settings_preferences_save'),
            ],
            'motifSave'    => $go('clinic_settings_booking_motif_save'),
            'motifArchive' => $this->urls->generate('clinic_settings_booking_motif_archive', ['motifId' => '__ID__']),
            'logoUpload'   => $go('clinic_settings_logo_upload'),
            'logoRemove'   => $go('clinic_settings_logo_remove'),
            'logo'         => null !== $settings->logoKey
                ? $this->urls->generate('clinic_settings_logo', ['v' => mb_substr(md5($settings->logoKey), 0, 8)])
                : null,
            'team'      => $go('clinic_settings_team'),
            'catalogue' => $go('clinic_catalogue'),
            // The pages a setting shows up on, for the « affects » chips.
            'aff' => [
                'agenda'   => $go('clinic_scheduling_agenda'),
                'planning' => $go('clinic_scheduling_planning'),
                'consult'  => $go('clinic_consultations'),
                'flux'     => $go('clinic_admission_queue'),
                'ordo'     => $go('clinic_prescriptions'),
                'fact'     => $go('clinic_invoices'),
                'stock'    => $go('clinic_stock'),
                'cat'      => $go('clinic_catalogue'),
                'equipe'   => $go('clinic_settings_team'),
            ],
        ];
    }

    /**
     * Known values first; the clinic's own, if it is not among them, after.
     *
     * @param array<string, string> $known
     *
     * @return list<array{0: string, 1: string}>
     */
    private static function pairs(array $known, ?string $current): array
    {
        $pairs = [];

        foreach ($known as $value => $label) {
            $pairs[] = [$value, $label];
        }

        if (null !== $current && !\array_key_exists($current, $known)) {
            $pairs[] = [$current, $current];
        }

        return $pairs;
    }

    private static function siret(?string $siret): ?string
    {
        if (null === $siret || 14 !== mb_strlen($siret)) {
            return $siret;
        }

        return implode(' ', [mb_substr($siret, 0, 3), mb_substr($siret, 3, 3), mb_substr($siret, 6, 3), mb_substr($siret, 9)]);
    }

    /** « FR20812456903 » → « FR 20 812456903 », as the field formats it. */
    private static function vat(?string $vat): ?string
    {
        if (null === $vat || 1 !== preg_match('/^([A-Z]{2})(\d{2})(\d+)$/', $vat, $m)) {
            return $vat;
        }

        return $m[1] . ' ' . $m[2] . ' ' . $m[3];
    }

    private static function iban(?string $iban): ?string
    {
        return null !== $iban ? mb_trim(chunk_split($iban, 4, ' ')) : null;
    }
}
