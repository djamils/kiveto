<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Settings;

use App\Context\Clinic\Application\Command\Clinic\RenameClinic\RenameClinic;
use App\Context\Clinic\Application\Command\Clinic\UpdateClinicLetterhead\UpdateClinicLetterhead;
use App\Presentation\Clinic\Security\ClinicPermissionGuard;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The identity a clinic prints on everything it hands out, saved as one block.
 */
#[Route(path: '/settings/identity', name: 'clinic_settings_identity_save', methods: ['POST'])]
final class UpdateClinicIdentityController extends AbstractController
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

        $name = mb_trim($request->request->getString('name'));

        if ('' === $name) {
            $this->addFlash('error', 'Le nom de la clinique ne peut pas être vide.');

            return $this->redirectToRoute('clinic_settings');
        }

        try {
            $this->commandBus->dispatch(new RenameClinic(
                clinicId: $clinicId->toString(),
                name: $name,
            ));

            $this->commandBus->dispatch(new UpdateClinicLetterhead(
                clinicId: $clinicId->toString(),
                legalName: $this->field($request, 'legalName'),
                legalForm: $this->field($request, 'legalForm'),
                shareCapital: $this->field($request, 'shareCapital'),
                siret: $this->field($request, 'siret'),
                vatNumber: $this->field($request, 'vatNumber'),
                rcsCity: $this->field($request, 'rcsCity'),
                rcsNumber: $this->field($request, 'rcsNumber'),
                apeCode: $this->field($request, 'apeCode'),
                ordinalNumber: $this->field($request, 'ordinalNumber'),
                streetLine1: $this->field($request, 'streetLine1'),
                streetLine2: $this->field($request, 'streetLine2'),
                postalCode: $this->field($request, 'postalCode'),
                city: $this->field($request, 'city'),
                countryCode: $this->field($request, 'countryCode'),
                phone: $this->field($request, 'phone'),
                email: $this->field($request, 'email'),
                website: $this->field($request, 'website'),
            ));
        } catch (\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('clinic_settings');
        } catch (\InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('clinic_settings');
        }

        $this->addFlash('success', 'Identité de la clinique enregistrée.');

        return $this->redirectToRoute('clinic_settings');
    }

    private function field(Request $request, string $name): ?string
    {
        $value = mb_trim($request->request->getString($name));

        return '' !== $value ? $value : null;
    }
}
