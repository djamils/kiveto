<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Settings;

use App\Context\Clinic\Application\Command\Clinic\UpdateClinicOpeningHours\UpdateClinicOpeningHours;
use App\Presentation\Clinic\Security\ClinicPermissionGuard;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The week the clinic keeps. The form posts every day at once — a week is read
 * as a whole, so it is saved as a whole.
 */
#[Route(path: '/settings/hours', name: 'clinic_settings_hours_save', methods: ['POST'])]
final class UpdateClinicHoursController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly ClinicPermissionGuard $guard,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $this->guard->require(ClinicPermissionGuard::MANAGE_STAFF);

        $clinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $clinicId);

        try {
            $this->commandBus->dispatch(new UpdateClinicOpeningHours(
                clinicId: $clinicId->toString(),
                week: $this->week($request),
                closures: $this->closures($request),
            ));
        } catch (\DomainException $exception) {
            $this->addFlash('error', $this->explain($exception));

            return $this->redirectToRoute('clinic_settings_hours');
        }

        $this->addFlash('success', 'Horaires enregistrés.');

        return $this->redirectToRoute('clinic_settings_hours');
    }

    /**
     * @return array<int, list<array{opensAt: string, closesAt: string}>>
     */
    private function week(Request $request): array
    {
        $week = [];

        for ($day = 1; $day <= 7; ++$day) {
            /** @var array<int, array{opensAt?: string, closesAt?: string}> $slots */
            $slots = $request->request->all('day')[$day] ?? [];

            foreach ($slots as $slot) {
                $opensAt  = mb_trim($slot['opensAt'] ?? '');
                $closesAt = mb_trim($slot['closesAt'] ?? '');

                // An empty pair is a row the user left blank, not a mistake.
                if ('' === $opensAt && '' === $closesAt) {
                    continue;
                }

                $week[$day][] = ['opensAt' => $opensAt, 'closesAt' => $closesAt];
            }
        }

        return $week;
    }

    /**
     * @return list<array{date: string, reason?: string|null}>
     */
    private function closures(Request $request): array
    {
        /** @var array<int, array{date?: string, reason?: string}> $rows */
        $rows = $request->request->all('closure');

        $closures = [];

        foreach ($rows as $row) {
            $date = mb_trim($row['date'] ?? '');

            if ('' === $date) {
                continue;
            }

            $closures[] = ['date' => $date, 'reason' => mb_trim($row['reason'] ?? '') ?: null];
        }

        return $closures;
    }

    private function explain(\DomainException $exception): string
    {
        $message = $exception->getMessage();

        if (str_contains($message, 'before it opens')) {
            return 'Une plage se termine avant de commencer : vérifiez les heures saisies.';
        }

        if (str_contains($message, 'overlap')) {
            return 'Deux plages du même jour se chevauchent.';
        }

        if (str_contains($message, 'HH:MM')) {
            return 'Les heures doivent être saisies au format 09:00.';
        }

        if (str_contains($message, 'calendar date')) {
            return 'La date de fermeture saisie n’est pas une date valide.';
        }

        return 'Les horaires saisis sont invalides.';
    }
}
