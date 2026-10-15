<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Shell;

use App\Context\Clinic\Application\Query\Clinic\GetClinicSettings\ClinicSettingsView;
use App\Context\Clinic\Application\Query\Clinic\GetClinicSettings\GetClinicSettings;
use App\Context\Clinic\Application\Query\Clinic\ListClinicsForUser\AccessibleClinic;
use App\Context\Clinic\Application\Query\Clinic\ListClinicsForUser\ListClinicsForUser;
use App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians\ClinicVeterinarianItem;
use App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians\ListClinicVeterinarians;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Inventory\Application\Query\WatchStock\StockWatchRow;
use App\Context\Inventory\Application\Query\WatchStock\WatchStock;
use App\Context\Inventory\Domain\StockAlert\ValueObject\StockAlertSeverity;
use App\Context\Scheduling\Application\Query\GetAgendaForClinicDateRange\AppointmentItem;
use App\Context\Scheduling\Application\Query\GetAgendaForClinicDateRange\GetAgendaForClinicDateRange;
use App\Presentation\Clinic\ViewModel\Shared\SheetText;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Builds what the application shell shows on every clinic page: the member's
 * clinics for the switcher, the member themselves for the topbar, and the few
 * live figures the shell surfaces (practitioners, today's appointments, critical
 * stock alerts). Every figure comes from a query; nothing is made up.
 */
final class ShellViewBuilder
{
    /** Square colours of the clinic switcher, handed out in the member's clinic order to clinics that chose none. */
    private const array CLINIC_COLORS = [
        'var(--pin)', '#16a34a', 'var(--ambre)', 'var(--hospit)', 'var(--labo)', 'var(--garance)',
    ];

    private const array ROLE_LABELS = [
        'MANAGER'              => 'Gérant',
        'VETERINARY'           => 'Vétérinaire',
        'VETERINARY_ASSISTANT' => 'ASV',
        'RECEPTIONIST'         => 'Accueil',
    ];

    /** The shell is rendered once per page: the view is kept for the request. */
    private ?ShellView $view = null;

    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly Security $security,
        private readonly ClockInterface $clock,
    ) {
    }

    public function build(): ShellView
    {
        return $this->view ??= $this->doBuild();
    }

    private function doBuild(): ShellView
    {
        $user            = $this->security->getUser();
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId()?->toString();
        $now             = $this->clock->now();

        if (!$user instanceof SecurityUser) {
            return $this->emptyView([], new ShellUser('Utilisateur', 'U', '', null), $now);
        }

        $accessible = $this->queryBus->ask(new ListClinicsForUser($user->id()));
        \assert(\is_array($accessible));

        $clinics     = [];
        $currentRole = null;

        foreach (array_values($accessible) as $index => $clinic) {
            \assert($clinic instanceof AccessibleClinic);

            $isCurrent = $clinic->clinicId === $currentClinicId;

            if ($isCurrent) {
                $currentRole = $clinic->memberRole;
            }

            $clinics[] = new ShellClinic(
                id: $clinic->clinicId,
                name: $clinic->clinicName,
                initial: mb_strtoupper(mb_substr($clinic->clinicName, 0, 1)),
                color: $clinic->brandColor ?? self::CLINIC_COLORS[$index % \count(self::CLINIC_COLORS)],
                subtitle: self::ROLE_LABELS[$clinic->memberRole->value],
                isCurrent: $isCurrent,
                isDefault: $clinic->isDefault,
            );
        }

        $current = null;

        foreach ($clinics as $clinic) {
            if ($clinic->isCurrent) {
                $current = $clinic;
            }
        }

        if (null === $current || null === $currentClinicId) {
            return $this->emptyView($clinics, $this->user($user, null, null), $now);
        }

        $practitioners = $this->queryBus->ask(new ListClinicVeterinarians($currentClinicId));
        \assert(\is_array($practitioners));

        $settings = $this->queryBus->ask(new GetClinicSettings($currentClinicId));
        $settings = $settings instanceof ClinicSettingsView ? $settings : null;
        $timeZone = new \DateTimeZone(null !== $settings ? $settings->timeZone : 'Europe/Paris');

        // The badge counts what the stock page files as critical: the same
        // reading of the shelves, so the two never disagree.
        $alerts = $this->queryBus->ask(new WatchStock($currentClinicId, $timeZone->getName()));
        \assert(\is_array($alerts));
        $critical = \count(array_filter(
            $alerts,
            static fn ($alert): bool => $alert instanceof StockWatchRow && StockAlertSeverity::CRITICAL === $alert->severity,
        ));

        return new ShellView(
            currentClinic: $current,
            clinics: $clinics,
            user: $this->user($user, $practitioners, $currentRole),
            todayLabel: $this->todayLabel($now->setTimezone($timeZone)),
            practitionerCount: \count($practitioners),
            appointmentsToday: $this->appointmentsToday($currentClinicId, $now, $timeZone),
            isOpenNow: null === $settings ? null : $this->isOpenNow($settings, $now->setTimezone($timeZone)),
            address: null === $settings ? null : $this->address($settings),
            stockAlertCount: $critical,
        );
    }

    /**
     * A shell with no current clinic: nothing to count yet.
     *
     * @param list<ShellClinic> $clinics
     */
    private function emptyView(array $clinics, ShellUser $user, \DateTimeImmutable $now): ShellView
    {
        return new ShellView(null, $clinics, $user, $this->todayLabel($now), 0, null, null, null, 0);
    }

    /**
     * @param array<mixed>|null $practitioners
     */
    private function user(SecurityUser $user, ?array $practitioners, ?ClinicMemberRole $role): ShellUser
    {
        $email = $user->getUserIdentifier();
        $name  = null;

        foreach ($practitioners ?? [] as $practitioner) {
            \assert($practitioner instanceof ClinicVeterinarianItem);

            if ($practitioner->userId === $user->id() && null !== $practitioner->displayName) {
                $name = $practitioner->displayName;
            }
        }

        return new ShellUser(
            name: $name ?? $email,
            initials: $this->initials($name ?? (string) strstr($email, '@', true)),
            email: $email,
            roleLabel: null === $role ? null : self::ROLE_LABELS[$role->value],
        );
    }

    private function initials(string $name): string
    {
        $words = preg_split('/[\s.\-_]+/u', $name, -1, \PREG_SPLIT_NO_EMPTY) ?: [];
        $words = array_values(array_filter(
            $words,
            static fn (string $word): bool => !\in_array(mb_strtolower($word), ['dr', 'pr'], true),
        ));

        if (\count($words) >= 2) {
            return mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[\count($words) - 1], 0, 1));
        }

        return '' === $name ? 'U' : mb_strtoupper(mb_substr($words[0] ?? $name, 0, 2));
    }

    private function todayLabel(\DateTimeImmutable $now): string
    {
        return SheetText::sentence(SheetText::date($now, 'EEEE d MMMM'));
    }

    private function appointmentsToday(string $clinicId, \DateTimeImmutable $now, \DateTimeZone $timeZone): int
    {
        $appointments = $this->queryBus->ask(GetAgendaForClinicDateRange::forDay($clinicId, $now, $timeZone));
        \assert(\is_array($appointments));

        return \count(array_filter(
            $appointments,
            static fn (mixed $appointment): bool => $appointment instanceof AppointmentItem
                && 'CANCELLED' !== $appointment->status,
        ));
    }

    /**
     * Whether the clinic is open right now by its own week and closures, or
     * null when it has not entered any opening hours yet.
     */
    private function isOpenNow(ClinicSettingsView $settings, \DateTimeImmutable $now): ?bool
    {
        if ([] === array_filter($settings->week)) {
            return null;
        }

        foreach ($settings->closures as $closure) {
            if ($closure['date'] === $now->format('Y-m-d')) {
                return false;
            }
        }

        $time = $now->format('H:i');

        foreach ($settings->week[(int) $now->format('N')] ?? [] as $slot) {
            if ($slot['opensAt'] <= $time && $time < $slot['closesAt']) {
                return true;
            }
        }

        return false;
    }

    private function address(ClinicSettingsView $settings): ?string
    {
        $street = $settings->letterhead['streetLine1'] ?? null;
        $city   = trim(($settings->letterhead['postalCode'] ?? '') . ' ' . ($settings->letterhead['city'] ?? ''));
        $parts  = array_filter([$street, $city], static fn (?string $part): bool => null !== $part && '' !== $part);

        return [] === $parts ? null : implode(', ', $parts);
    }
}
