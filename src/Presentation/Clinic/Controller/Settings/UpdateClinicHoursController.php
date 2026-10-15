<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Settings;

use App\Context\Clinic\Application\Command\Clinic\UpdateClinicOpeningHours\UpdateClinicOpeningHours;
use App\Presentation\Clinic\Security\ClinicPermissionGuard;
use App\Presentation\Clinic\ViewModel\Settings\SettingsEndpoint;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The week the clinic keeps and the days it is shut, saved whole on every
 * change — a week is read as a whole, so it is saved as a whole.
 *
 * The page edits closures as ranges (« du 24 au 26 décembre, Noël »); the
 * clinic keeps them day by day, so each range is spelled out here.
 */
#[Route(path: '/settings/hours', name: 'clinic_settings_hours_save', methods: ['POST'])]
final readonly class UpdateClinicHoursController
{
    /** A closure longer than this is a typing slip, not a holiday. */
    private const int MAX_CLOSURE_DAYS = 92;

    public function __construct(
        private CommandBusInterface $commandBus,
        private SettingsEndpoint $endpoint,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        if (null !== $refused = $this->endpoint->guard($request, ClinicPermissionGuard::MANAGE_STAFF)) {
            return $refused;
        }

        $values = SettingsEndpoint::values($request);

        $closures = self::closures($values['closures'] ?? null);

        if (\is_string($closures)) {
            return $this->endpoint->refuse($closures, 'fermetures');
        }

        try {
            $this->commandBus->dispatch(new UpdateClinicOpeningHours(
                clinicId: $this->endpoint->clinicId(),
                week: self::week($values['week'] ?? null),
                closures: $closures,
            ));
        } catch (\LogicException $exception) {
            return $this->endpoint->refuse(self::explain($exception), 'horaires');
        }

        return $this->endpoint->ok();
    }

    /**
     * Seven days, Monday first, each a list of [opens, closes] pairs.
     *
     * @return array<int, list<array{opensAt: string, closesAt: string}>>
     */
    private static function week(mixed $raw): array
    {
        $week = [];

        if (!\is_array($raw)) {
            return $week;
        }

        foreach (array_values($raw) as $index => $slots) {
            if ($index > 6 || !\is_array($slots)) {
                continue;
            }

            foreach ($slots as $slot) {
                if (!\is_array($slot)) {
                    continue;
                }

                $opensAt  = \is_string($slot[0] ?? null) ? mb_trim($slot[0]) : '';
                $closesAt = \is_string($slot[1] ?? null) ? mb_trim($slot[1]) : '';

                // An empty pair is a row the user left blank, not a mistake.
                if ('' === $opensAt && '' === $closesAt) {
                    continue;
                }

                $week[$index + 1][] = ['opensAt' => $opensAt, 'closesAt' => $closesAt];
            }
        }

        return $week;
    }

    /**
     * @return list<array{date: string, reason?: string|null}>|string the days, or why the ranges were refused
     */
    private static function closures(mixed $raw): array|string
    {
        if (!\is_array($raw)) {
            return [];
        }

        $days = [];

        foreach ($raw as $range) {
            if (!\is_array($range)) {
                continue;
            }

            $from   = self::date($range['du'] ?? null);
            $to     = self::date($range['au'] ?? null) ?? $from;
            $reason = \is_string($range['motif'] ?? null) ? mb_trim($range['motif']) : '';

            if (null === $from || null === $to) {
                return 'Une fermeture n’a pas de date valide.';
            }

            if ($to < $from) {
                return 'Une fermeture se termine avant de commencer.';
            }

            if ($from->diff($to)->days >= self::MAX_CLOSURE_DAYS) {
                return 'Une fermeture ne peut pas dépasser trois mois.';
            }

            for ($day = $from; $day <= $to; $day = $day->modify('+1 day')) {
                $days[$day->format('Y-m-d')] = ['date' => $day->format('Y-m-d'), 'reason' => '' !== $reason ? $reason : null];
            }
        }

        ksort($days);

        return array_values($days);
    }

    private static function date(mixed $raw): ?\DateTimeImmutable
    {
        if (!\is_string($raw)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', mb_trim($raw));

        return false !== $date && $date->format('Y-m-d') === mb_trim($raw) ? $date : null;
    }

    private static function explain(\LogicException $exception): string
    {
        $message = $exception->getMessage();

        return match (true) {
            str_contains($message, 'before it opens') => 'Une plage se termine avant de commencer : vérifiez les heures saisies.',
            str_contains($message, 'overlap')         => 'Deux plages du même jour se chevauchent.',
            str_contains($message, 'HH:MM')           => 'Les heures doivent être saisies au format 09:00.',
            default                                   => 'Les horaires saisis sont invalides.',
        };
    }
}
