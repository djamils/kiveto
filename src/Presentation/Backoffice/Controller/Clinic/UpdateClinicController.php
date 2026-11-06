<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\Clinic;

use App\Context\Clinic\Application\Command\Account\ReviseClinicAccount\ReviseClinicAccount;
use App\Context\Clinic\Application\Command\Clinic\ChangeClinicTimeZone\ChangeClinicTimeZone;
use App\Context\Clinic\Application\Command\Clinic\RenameClinic\RenameClinic;
use App\Context\Clinic\Application\Command\Clinic\UpdateClinicLetterhead\UpdateClinicLetterhead;
use App\Context\Clinic\Application\Query\Clinic\GetClinicSettings\ClinicSettingsView;
use App\Context\Clinic\Application\Query\Clinic\GetClinicSettings\GetClinicSettings;
use App\Context\Clinic\Domain\Account\ClinicPlan;
use App\Context\Clinic\Domain\Account\ParallelSoftware;
use App\Presentation\Backoffice\Audit\ConsoleAuditTrail;
use App\Presentation\Backoffice\Controller\Account\ShowAccountController;
use App\Presentation\Backoffice\ViewModel\ConsoleFormat;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\System\Audit\Domain\ValueObject\AuditCategory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * C4 — « Modifier la clinique · Enregistrer »: name, contact and address of
 * the establishment, time zone, then the account (plan, pilot end, parallel
 * software, internal note). The company (SIRET, VAT) is kept.
 */
final class UpdateClinicController extends AbstractController
{
    public const string CSRF_ID = 'backoffice_clinic';

    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
        private readonly ConsoleAuditTrail $auditTrail,
    ) {
    }

    #[Route(path: '/clinics/{id}/update', name: 'backoffice_clinic_update', requirements: ['id' => ShowClinicController::UUID], methods: ['POST'])]
    public function __invoke(Request $request, string $id): RedirectResponse
    {
        if (!$this->isCsrfTokenValid(self::CSRF_ID, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $settings = $this->queryBus->ask(new GetClinicSettings($id));
        \assert($settings instanceof ClinicSettingsView);

        $form     = $request->request;
        $name     = trim($form->getString('name'));
        $zone     = $form->getString('time_zone');
        $plan     = ClinicPlan::tryFrom($form->getString('plan'));
        $software = ParallelSoftware::tryFrom($form->getString('parallel_software'));
        $l        = $settings->letterhead;

        try {
            if ('' !== $name && $name !== $settings->name) {
                $this->commandBus->dispatch(new RenameClinic($id, $name));
            }

            if (\in_array($zone, ShowAccountController::TIME_ZONES, true) && $zone !== $settings->timeZone) {
                $this->commandBus->dispatch(new ChangeClinicTimeZone($id, $zone));
            }

            $this->commandBus->dispatch(new UpdateClinicLetterhead(
                clinicId: $id,
                legalName: $l['legalName'] ?? null,
                legalForm: $l['legalForm'] ?? null,
                shareCapital: $l['shareCapital'] ?? null,
                siret: $l['siret'] ?? null,
                vatNumber: $l['vatNumber'] ?? null,
                rcsCity: $l['rcsCity'] ?? null,
                rcsNumber: $l['rcsNumber'] ?? null,
                apeCode: $l['apeCode'] ?? null,
                ordinalNumber: $l['ordinalNumber'] ?? null,
                streetLine1: self::text($form->getString('street')),
                streetLine2: $l['streetLine2'] ?? null,
                postalCode: self::text($form->getString('postal_code')),
                city: self::text($form->getString('city')),
                countryCode: $l['countryCode'] ?? 'FR',
                phone: ConsoleFormat::phone($form->getString('phone')),
                email: self::text($form->getString('email')),
                website: $l['website'] ?? null,
                ordinalCouncil: $l['ordinalCouncil'] ?? null,
                liabilityInsurer: $l['liabilityInsurer'] ?? null,
                liabilityPolicyNumber: $l['liabilityPolicyNumber'] ?? null,
            ));

            if (null !== $plan) {
                $this->commandBus->dispatch(new ReviseClinicAccount(
                    clinicId: $id,
                    plan: $plan->value,
                    pilotEndsOn: self::text($form->getString('pilot_ends_on')),
                    parallelSoftware: $software?->value,
                    internalNote: $form->getString('internal_note'),
                ));
            }
        } catch (\LogicException $exception) {
            $message = $exception->getMessage();
            $this->addFlash('error', match (true) {
                str_contains($message, 'phone') => 'Ce numéro de téléphone n’est pas valide.',
                str_contains($message, 'mail')  => 'Cette adresse e-mail n’est pas valide.',
                default                         => $message,
            });

            return $this->redirectToRoute('backoffice_clinic_show', ['id' => $id, 'dialog' => 'edit']);
        }

        $this->auditTrail->record('clinic.updated', AuditCategory::CLINICS, 'Fiche de la clinique modifiée', clinicId: $id, clinicName: '' !== $name ? $name : $settings->name);
        $this->addFlash('success', 'Clinique enregistrée.');

        return $this->redirectToRoute('backoffice_clinic_show', ['id' => $id]);
    }

    private static function text(string $value): ?string
    {
        $value = trim($value);

        return '' === $value ? null : $value;
    }
}
