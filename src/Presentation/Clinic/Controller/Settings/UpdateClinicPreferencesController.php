<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Settings;

use App\Context\Clinic\Application\Command\Clinic\ChangeClinicLocale\ChangeClinicLocale;
use App\Context\Clinic\Application\Command\Clinic\ChangeClinicTimeZone\ChangeClinicTimeZone;
use App\Presentation\Clinic\Security\ClinicPermissionGuard;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The language the clinic works in and the clock it works by.
 */
#[Route(path: '/settings/preferences', name: 'clinic_settings_preferences_save', methods: ['POST'])]
final class UpdateClinicPreferencesController extends AbstractController
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

        $locale   = mb_trim($request->request->getString('locale'));
        $timeZone = mb_trim($request->request->getString('timeZone'));

        try {
            if ('' !== $locale) {
                $this->commandBus->dispatch(new ChangeClinicLocale(
                    clinicId: $clinicId->toString(),
                    locale: $locale,
                ));
            }

            if ('' !== $timeZone) {
                $this->commandBus->dispatch(new ChangeClinicTimeZone(
                    clinicId: $clinicId->toString(),
                    timeZone: $timeZone,
                ));
            }
        } catch (\InvalidArgumentException $exception) {
            $this->addFlash('error', 'La langue ou le fuseau horaire choisi n’est pas reconnu.');

            return $this->redirectToRoute('clinic_settings_preferences');
        }

        $this->addFlash('success', 'Préférences enregistrées.');

        return $this->redirectToRoute('clinic_settings_preferences');
    }
}
