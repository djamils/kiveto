<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Consultation\View;

use App\Context\Consultation\Application\Query\GetConsultationDetails\ConsultationDetailsDTO;
use App\Context\Consultation\Application\Query\GetConsultationDetails\GetConsultationDetails;
use App\Presentation\Clinic\ViewModel\Consultation\CockpitPatientBuilder;
use App\Presentation\Clinic\ViewModel\Consultation\CockpitReferenceData;
use App\Presentation\Clinic\ViewModel\Consultation\CockpitStateBuilder;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use App\Shared\Domain\Time\ClockInterface;
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

        $patient = $this->patientBuilder->build($consultation->patientId, $clinicId, $id);

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
            ],
            'pageTitle' => 'Consultation',
        ]);
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
