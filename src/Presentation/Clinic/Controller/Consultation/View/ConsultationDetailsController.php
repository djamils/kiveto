<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Consultation\View;

use App\Context\Billing\Application\Query\FindInvoiceForAdmission\AdmissionInvoiceRow;
use App\Context\Billing\Application\Query\FindInvoiceForAdmission\FindInvoiceForAdmission;
use App\Context\Client\Application\Query\GetClientNotificationContact\ClientNotificationContact;
use App\Context\Client\Application\Query\GetClientNotificationContact\GetClientNotificationContact;
use App\Context\Consultation\Application\Query\GetConsultationDetails\ConsultationDetailsDTO;
use App\Context\Consultation\Application\Query\GetConsultationDetails\GetConsultationDetails;
use App\Context\Prescription\Application\Query\ListPrescriptionsForConsultation\ConsultationPrescriptionRow;
use App\Context\Prescription\Application\Query\ListPrescriptionsForConsultation\ListPrescriptionsForConsultation;
use App\Presentation\Clinic\ViewModel\Consultation\CockpitContextBuilder;
use App\Presentation\Clinic\ViewModel\Consultation\CockpitPatientBuilder;
use App\Presentation\Clinic\ViewModel\Consultation\CockpitReferenceData;
use App\Presentation\Clinic\ViewModel\Consultation\CockpitStateBuilder;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ConsultationDetailsController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly CockpitStateBuilder $stateBuilder,
        private readonly CockpitPatientBuilder $patientBuilder,
        private readonly CockpitReferenceData $referenceData,
        private readonly CockpitContextBuilder $contextBuilder,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('/clinic/consultations/{id}', name: 'clinic_consultation_details', methods: ['GET'])]
    public function __invoke(string $id): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $clinicId = $currentClinicId->toString();

        try {
            $consultation = $this->queryBus->ask(new GetConsultationDetails($id, $clinicId));
        } catch (\DomainException $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        \assert($consultation instanceof ConsultationDetailsDTO);

        $patient    = $this->patientBuilder->build($consultation->patientId, $clinicId, $id);
        $letterhead = $this->contextBuilder->letterhead($clinicId);
        $animalId   = \is_string($patient['patient']['animalId'] ?? null) ? $patient['patient']['animalId'] : null;
        $clientId   = \is_string($patient['owner']['clientId'] ?? null) ? $patient['owner']['clientId'] : null;
        $team       = $this->stateBuilder->team($clinicId);
        $user       = $this->getUser();

        return $this->render('clinic/consultation/detail/index.html.twig', [
            'consultation' => $consultation,
            'patient'      => $patient['patient'],
            'owner'        => $patient['owner'],
            'hydration'    => [
                'consultation' => $this->stateBuilder->build($consultation),
                'patient'      => $patient['patient'],
                'owner'        => $patient['owner'],
                'history'      => $patient['history'],
                'reference'    => $this->referenceData->toArray(),
                'serverNowUtc' => $this->clock->now()->format('Y-m-d H:i:s'),
                'endpoints'    => $this->endpoints($id),
                'documents'    => $this->documents($id, $clinicId, $consultation->admissionId, $clientId),
                'context'      => [
                    'origin'       => $this->contextBuilder->origin($consultation, $letterhead['timeZone']),
                    'letterhead'   => $letterhead,
                    'due'          => $this->contextBuilder->due($animalId, $clinicId),
                    'practitioner' => $team[$consultation->practitionerUserId] ?? null,
                    'team'         => array_values($team),
                    'me'           => $user instanceof SecurityUser ? ($team[$user->id()] ?? null) : null,
                    'urls'         => [
                        'flux'      => $this->generateUrl('clinic_admission_queue'),
                        'animal'    => null !== $animalId ? $this->generateUrl('clinic_animals_view', ['id' => $animalId]) : null,
                        'client'    => null !== $clientId ? $this->generateUrl('clinic_clients_view', ['id' => $clientId]) : null,
                        'checkout'  => $this->generateUrl('clinic_checkout', ['admissionId' => $consultation->admissionId]),
                        'prescribe' => $this->generateUrl('clinic_prescription_prepare', ['consultationId' => $id]),
                        'agenda'    => $this->generateUrl('clinic_scheduling_agenda'),
                        'catalog'   => $this->generateUrl('clinic_catalogue'),
                    ],
                ],
            ],
            'pageTitle' => 'Consultation',
        ]);
    }

    /**
     * What the "Imprimer / Télécharger" panel can offer: the report is always
     * there, the ordonnances and the invoice only once they have been signed.
     * The ids let « Envoyer par e-mail » name the ticked documents; the
     * recipient is the owner's e-mail on file (null: the button stays off).
     *
     * @return array{
     *     report: string,
     *     bundle: string,
     *     prescriptions: list<array{id: string, number: string, lineCount: int, url: string}>,
     *     invoice: array{id: string, number: string, url: string, sendable: bool}|null,
     *     send: array{url: string, recipient: array{name: string, email: string}|null}
     * }
     */
    private function documents(string $consultationId, string $clinicId, string $admissionId, ?string $clientId): array
    {
        $prescriptions = [];
        $rows          = $this->queryBus->ask(new ListPrescriptionsForConsultation(
            clinicId: $clinicId,
            consultationId: $consultationId,
        ));

        if (\is_array($rows)) {
            foreach ($rows as $row) {
                if (!$row instanceof ConsultationPrescriptionRow || !$row->isPrintable() || null === $row->number) {
                    continue;
                }

                $prescriptions[] = [
                    'id'        => $row->id,
                    'number'    => $row->number,
                    'lineCount' => $row->lineCount,
                    'url'       => $this->generateUrl('clinic_prescription_pdf', ['prescriptionId' => $row->id]),
                ];
            }
        }

        $invoiceRow = $this->queryBus->ask(new FindInvoiceForAdmission(
            clinicId: $clinicId,
            admissionId: $admissionId,
        ));

        $invoice = $invoiceRow instanceof AdmissionInvoiceRow && $invoiceRow->isPrintable() && null !== $invoiceRow->number
            ? [
                'id'     => $invoiceRow->id,
                'number' => $invoiceRow->number,
                'url'    => $this->generateUrl('clinic_invoice_pdf', ['invoiceId' => $invoiceRow->id]),
                // A credited invoice is printed but not e-mailed.
                'sendable' => $invoiceRow->isSendable(),
            ]
            : null;

        return [
            'report'        => $this->generateUrl('clinic_consultation_report_pdf', ['consultationId' => $consultationId]),
            'bundle'        => $this->generateUrl('clinic_consultation_bundle_pdf', ['consultationId' => $consultationId]),
            'prescriptions' => $prescriptions,
            'invoice'       => $invoice,
            'send'          => [
                'url'       => $this->generateUrl('clinic_consultation_send_documents', ['id' => $consultationId]),
                'recipient' => null !== $clientId ? $this->recipient($clinicId, $clientId) : null,
            ],
        ];
    }

    /**
     * The owner as the documents e-mail reaches them, or null when they have
     * no e-mail or are archived.
     *
     * @return array{name: string, email: string}|null
     */
    private function recipient(string $clinicId, string $clientId): ?array
    {
        $contact = $this->queryBus->ask(new GetClientNotificationContact($clinicId, $clientId));

        return $contact instanceof ClientNotificationContact && !$contact->archived && null !== $contact->email
            ? ['name' => $contact->displayName, 'email' => $contact->email]
            : null;
    }

    /**
     * Route table handed to the page module so the JS never builds URLs itself.
     *
     * @return array<string, string>
     */
    private function endpoints(string $consultationId): array
    {
        $routes = [
            'chiefComplaint'         => 'clinic_consultation_record_chief_complaint',
            'motifs'                 => 'clinic_consultation_update_motifs',
            'vitals'                 => 'clinic_consultation_record_vitals',
            'typedVitalRecord'       => 'clinic_consultation_record_typed_vital',
            'typedVitalRemove'       => 'clinic_consultation_remove_typed_vital',
            'subjective'             => 'clinic_consultation_update_subjective',
            'examSystem'             => 'clinic_consultation_record_exam_system',
            'examSystemsAllNormal'   => 'clinic_consultation_mark_all_systems_normal',
            'objective'              => 'clinic_consultation_update_objective',
            'diagnosisAdd'           => 'clinic_consultation_add_diagnosis',
            'diagnosisUpdate'        => 'clinic_consultation_update_diagnosis',
            'diagnosisRemove'        => 'clinic_consultation_remove_diagnosis',
            'diagnosisPrimary'       => 'clinic_consultation_set_primary_diagnosis',
            'planActionAdd'          => 'clinic_consultation_add_plan_action',
            'planActionUpdate'       => 'clinic_consultation_update_plan_action',
            'planActionRemove'       => 'clinic_consultation_remove_plan_action',
            'prescriptionLineAdd'    => 'clinic_consultation_add_prescription_line',
            'prescriptionLineRemove' => 'clinic_consultation_remove_prescription_line',
            'teamMemo'               => 'clinic_consultation_update_team_memo',
            'catalogSearch'          => 'clinic_consultation_catalog_search',
            'noteAdd'                => 'clinic_consultation_add_note',
            'addendum'               => 'clinic_consultation_add_addendum',
            'close'                  => 'clinic_consultation_close',
        ];

        $endpoints = [];

        foreach ($routes as $key => $route) {
            $endpoints[$key] = $this->generateUrl($route, ['id' => $consultationId]);
        }

        $endpoints['admissionQueue'] = $this->generateUrl('clinic_admission_queue');
        $endpoints['consultations']  = $this->generateUrl('clinic_consultations');

        return $endpoints;
    }
}
