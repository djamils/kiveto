<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Settings;

use App\Context\Clinic\Application\Command\Clinic\ChangeClinicBrandColor\ChangeClinicBrandColor;
use App\Context\Clinic\Application\Command\Clinic\ChangeClinicLocale\ChangeClinicLocale;
use App\Context\Clinic\Application\Command\Clinic\ChangeClinicTimeZone\ChangeClinicTimeZone;
use App\Context\Clinic\Application\Command\Clinic\RenameClinic\RenameClinic;
use App\Context\Clinic\Application\Command\Clinic\UpdateClinicLetterhead\UpdateClinicLetterhead;
use App\Context\Clinic\Domain\Exception\InvalidLetterheadException;
use App\Context\Clinic\Domain\Exception\InvoicePrefixAlreadyUsedException;
use App\Presentation\Clinic\Security\ClinicPermissionGuard;
use App\Presentation\Clinic\ViewModel\Settings\SettingsEndpoint;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The « Identité » section, saved whole on every change: the name, the
 * letterhead, the colour, the language and the time zone of the clinic.
 */
#[Route(path: '/settings/identity', name: 'clinic_settings_identity_save', methods: ['POST'])]
final readonly class UpdateClinicIdentityController
{
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

        $v        = SettingsEndpoint::values($request);
        $clinicId = $this->endpoint->clinicId();
        $current  = $this->endpoint->settings();
        $name     = SettingsEndpoint::text($v, 'id.nom');

        if (null === $name) {
            return $this->endpoint->refuse('Le nom de la clinique est obligatoire.', 'id.nom');
        }

        try {
            $this->commandBus->dispatch(new UpdateClinicLetterhead(
                clinicId: $clinicId,
                legalName: SettingsEndpoint::text($v, 'id.rs'),
                legalForm: SettingsEndpoint::text($v, 'id.forme'),
                shareCapital: SettingsEndpoint::text($v, 'id.capital'),
                siret: SettingsEndpoint::compact($v, 'id.siret'),
                vatNumber: SettingsEndpoint::compact($v, 'id.tva'),
                rcsCity: SettingsEndpoint::text($v, 'id.rcsVille'),
                rcsNumber: SettingsEndpoint::text($v, 'id.rcsN'),
                apeCode: SettingsEndpoint::compact($v, 'id.ape'),
                ordinalNumber: SettingsEndpoint::text($v, 'id.ordreN'),
                streetLine1: SettingsEndpoint::text($v, 'id.adr1'),
                streetLine2: SettingsEndpoint::text($v, 'id.adr2'),
                postalCode: SettingsEndpoint::text($v, 'id.cp'),
                city: SettingsEndpoint::text($v, 'id.ville'),
                countryCode: SettingsEndpoint::text($v, 'id.pays') ?? $current->countryCode,
                phone: SettingsEndpoint::phone(SettingsEndpoint::text($v, 'id.tel')),
                email: SettingsEndpoint::text($v, 'id.mail'),
                website: SettingsEndpoint::text($v, 'id.web'),
                ordinalCouncil: SettingsEndpoint::text($v, 'id.cro'),
                liabilityInsurer: SettingsEndpoint::text($v, 'id.rcpA'),
                liabilityPolicyNumber: SettingsEndpoint::compact($v, 'id.rcpN'),
            ));

            $this->commandBus->dispatch(new RenameClinic(clinicId: $clinicId, name: $name));
            $this->commandBus->dispatch(new ChangeClinicBrandColor(clinicId: $clinicId, brandColor: SettingsEndpoint::text($v, 'id.couleur')));

            $locale = SettingsEndpoint::text($v, 'id.locale');
            if (null !== $locale && $locale !== $current->locale) {
                $this->commandBus->dispatch(new ChangeClinicLocale(clinicId: $clinicId, locale: $locale));
            }

            $timeZone = SettingsEndpoint::text($v, 'id.tz');
            if (null !== $timeZone && $timeZone !== $current->timeZone) {
                $this->commandBus->dispatch(new ChangeClinicTimeZone(clinicId: $clinicId, timeZone: $timeZone));
            }
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
        if ($exception instanceof InvalidLetterheadException) {
            return self::explainFiscalIdentity($exception);
        }

        if ($exception instanceof InvoicePrefixAlreadyUsedException) {
            return ['Ce préfixe est déjà utilisé par un autre établissement du même SIREN.', 'id.siret'];
        }

        $message = $exception->getMessage();

        return match (true) {
            str_contains($message, 'SIRET')        => ['SIRET invalide : vérifiez les 14 chiffres.', 'id.siret'],
            str_contains($message, 'phone')        => ['Ce numéro de téléphone n’est pas valide.', 'id.tel'],
            str_contains($message, 'email')        => ['Adresse e-mail invalide.', 'id.mail'],
            str_contains($message, 'council')      => ['Conseil régional de l’Ordre inconnu.', 'id.cro'],
            str_contains($message, 'hex color')    => ['Cette couleur n’est pas valide (format #rrggbb).', 'id.couleur'],
            str_contains($message, 'Country code') => ['Le pays de l’adresse n’est pas valide.', 'id.adr1'],
            str_contains($message, 'locale')       => ['Cette langue n’est pas reconnue.', 'id.locale'],
            str_contains($message, 'timezone')     => ['Ce fuseau horaire n’est pas reconnu.', 'id.tz'],
            default                                => ['L’identité saisie est invalide.', null],
        };
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function explainFiscalIdentity(InvalidLetterheadException $exception): array
    {
        if (InvalidLetterheadException::FIELD_SIRET === $exception->field) {
            return InvalidLetterheadException::REASON_MISSING === $exception->reason
                ? ['Renseignez le SIRET pour enregistrer un n° de TVA.', 'id.siret']
                : ['SIRET invalide : vérifiez les 14 chiffres.', 'id.siret'];
        }

        return match ($exception->reason) {
            InvalidLetterheadException::REASON_SIREN_MISMATCH => ['N° de TVA : il ne correspond pas au SIRET.', 'id.tva'],
            InvalidLetterheadException::REASON_KEY            => ['N° de TVA invalide : clé incorrecte.', 'id.tva'],
            default                                           => ['N° de TVA invalide : format FR + 2 chiffres + SIREN.', 'id.tva'],
        };
    }
}
