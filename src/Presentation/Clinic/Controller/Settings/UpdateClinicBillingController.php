<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Settings;

use App\Context\Clinic\Application\Command\Clinic\UpdateClinicBillingSettings\UpdateClinicBillingSettings;
use App\Presentation\Clinic\Security\ClinicPermissionGuard;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Where the invoice is issued from, how its number is built and where the money
 * goes.
 */
#[Route(path: '/settings/billing', name: 'clinic_settings_billing_save', methods: ['POST'])]
final class UpdateClinicBillingController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly ClinicPermissionGuard $guard,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $this->guard->require(ClinicPermissionGuard::MANAGE_BILLING);

        $clinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $clinicId);

        $terms    = mb_trim($request->request->getString('paymentTermsDays'));
        $termDays = '' !== $terms ? (int) $terms : null;

        try {
            $this->commandBus->dispatch(new UpdateClinicBillingSettings(
                clinicId: $clinicId->toString(),
                streetLine1: $this->field($request, 'streetLine1'),
                streetLine2: $this->field($request, 'streetLine2'),
                postalCode: $this->field($request, 'postalCode'),
                city: $this->field($request, 'city'),
                countryCode: $this->field($request, 'countryCode'),
                invoiceNumberPrefix: $this->field($request, 'invoiceNumberPrefix'),
                paymentTermsDays: $termDays,
                iban: $this->field($request, 'iban'),
                bic: $this->field($request, 'bic'),
                invoiceFooterNote: $this->field($request, 'invoiceFooterNote'),
            ));
        } catch (\DomainException $exception) {
            $this->addFlash('error', $this->explain($exception));

            return $this->redirectToRoute('clinic_settings_billing');
        }

        $this->addFlash('success', 'Paramètres de facturation enregistrés.');

        return $this->redirectToRoute('clinic_settings_billing');
    }

    private function field(Request $request, string $name): ?string
    {
        $value = mb_trim($request->request->getString($name));

        return '' !== $value ? $value : null;
    }

    private function explain(\DomainException $exception): string
    {
        $message = $exception->getMessage();

        if (str_contains($message, 'IBAN')) {
            return 'L’IBAN saisi n’est pas un IBAN valide.';
        }

        if (str_contains($message, 'BIC')) {
            return 'Le BIC saisi n’est pas un BIC valide.';
        }

        if (str_contains($message, 'prefix')) {
            return 'Le préfixe de numérotation n’accepte que lettres, chiffres, tirets et barres obliques.';
        }

        if (str_contains($message, 'Payment terms')) {
            return 'Le délai de règlement doit être compris entre 0 et 365 jours.';
        }

        return 'Les paramètres de facturation saisis sont invalides.';
    }
}
