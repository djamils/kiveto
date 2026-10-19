<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Settings;

use App\Context\Clinic\Application\Command\Clinic\UpdateClinicBillingSettings\UpdateClinicBillingSettings;
use App\Context\Clinic\Domain\Exception\InvoicePrefixAlreadyUsedException;
use App\Presentation\Clinic\Security\ClinicPermissionGuard;
use App\Presentation\Clinic\ViewModel\Settings\SettingsEndpoint;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The « Facturation et caisse » section, saved whole on every change: how the
 * invoices and credit notes are numbered, when they are due, what the desk
 * takes, the VAT regime, where the money goes and what is printed at the foot.
 */
#[Route(path: '/settings/billing', name: 'clinic_settings_billing_save', methods: ['POST'])]
final readonly class UpdateClinicBillingController
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private SettingsEndpoint $endpoint,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        if (null !== $refused = $this->endpoint->guard($request, ClinicPermissionGuard::MANAGE_BILLING)) {
            return $refused;
        }

        $v       = SettingsEndpoint::values($request);
        $current = $this->endpoint->settings();
        $methods = $v['fa.moyens'] ?? null;

        if (\is_array($methods) && [] === $methods) {
            return $this->endpoint->refuse('Gardez au moins un moyen de paiement.', 'fa.moyens');
        }

        try {
            $this->commandBus->dispatch(new UpdateClinicBillingSettings(
                clinicId: $current->id,
                streetLine1: SettingsEndpoint::text($v, 'fa.adr1'),
                streetLine2: SettingsEndpoint::text($v, 'fa.adr2'),
                postalCode: SettingsEndpoint::text($v, 'fa.cp'),
                city: SettingsEndpoint::text($v, 'fa.ville'),
                countryCode: $current->countryCode,
                invoiceNumberPrefix: SettingsEndpoint::text($v, 'fa.prefixe'),
                paymentTermsDays: SettingsEndpoint::int($v, 'fa.cond'),
                iban: SettingsEndpoint::compact($v, 'fa.iban'),
                bic: SettingsEndpoint::compact($v, 'fa.bic'),
                invoiceFooterNote: \is_string($v['fa.mentions'] ?? null) && '' !== mb_trim($v['fa.mentions']) ? mb_trim($v['fa.mentions']) : null,
                creditNotePrefix: SettingsEndpoint::text($v, 'fa.av'),
                professionalPaymentTermsDays: SettingsEndpoint::int($v, 'fa.pro'),
                vatRegime: SettingsEndpoint::text($v, 'fe.regime'),
                vatOnDebits: true === ($v['fe.debits'] ?? false),
                acceptedPaymentMethods: \is_array($methods) ? array_values(array_filter($methods, \is_string(...))) : null,
            ));
        } catch (\LogicException $exception) {
            [$reason, $field] = self::explain($exception);

            return $this->endpoint->refuse($reason, $field);
        }

        return $this->endpoint->ok();
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    private static function explain(\LogicException $exception): array
    {
        if ($exception instanceof InvoicePrefixAlreadyUsedException) {
            return [
                'Ce préfixe est déjà utilisé par un autre établissement du même SIREN.',
                InvoicePrefixAlreadyUsedException::SERIES_CREDIT_NOTE === $exception->series ? 'fa.av' : 'fa.prefixe',
            ];
        }

        $message = $exception->getMessage();

        return match (true) {
            str_contains($message, 'IBAN')           => ['L’IBAN saisi n’est pas un IBAN valide.', 'fa.iban'],
            str_contains($message, 'BIC')            => ['Le BIC saisi n’est pas un BIC valide.', 'fa.bic'],
            str_contains($message, 'prefix')         => ['Le préfixe de numérotation n’accepte que lettres, chiffres, tirets et barres obliques.', 'fa.prefixe'],
            str_contains($message, 'Payment terms')  => ['Le délai de règlement doit être compris entre 0 et 365 jours.', 'fa.pro'],
            str_contains($message, 'Payment method') => ['Ce moyen de paiement n’est pas proposé.', 'fa.moyens'],
            str_contains($message, 'VAT regime')     => ['Ce régime de TVA n’est pas reconnu.', 'fe.regime'],
            default                                  => ['Les paramètres de facturation saisis sont invalides.', null],
        };
    }
}
