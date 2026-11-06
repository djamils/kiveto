<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\Clinic;

use App\Presentation\Backoffice\Feature\ConsoleFeatures;
use App\Presentation\Backoffice\ViewModel\Clinic\ClinicLifecycle;
use App\Presentation\Backoffice\ViewModel\Clinic\ClinicListBuilder;
use App\Presentation\Backoffice\ViewModel\Clinic\ClinicListRow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * B1 / B2 — « Cliniques ».
 */
final class ListClinicsController extends AbstractController
{
    public function __construct(
        private readonly ClinicListBuilder $builder,
        private readonly ConsoleFeatures $features,
    ) {
    }

    #[Route(path: '/clinics', name: 'backoffice_clinics', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $status = $request->query->getString('status', 'all');
        $status = \in_array($status, [ClinicLifecycle::ACTIVE, ClinicLifecycle::ONBOARDING, ClinicLifecycle::SUSPENDED], true) ? $status : 'all';
        $sort   = $request->query->getString('sort', 'activity');
        $sort   = \array_key_exists($sort, ClinicListBuilder::SORTS) ? $sort : 'activity';
        $search = trim($request->query->getString('q'));
        $todo   = $request->query->getString('todo');
        $todo   = \array_key_exists($todo, ClinicListBuilder::ALERT_KINDS) ? $todo : '';

        $list     = $this->builder->build($status, $search, $sort, max(1, $request->query->getInt('page', 1)));
        $features = $this->features->counts(array_map(static fn (ClinicListRow $row): string => $row->clinic->id, $list['rows']));

        return $this->render('backoffice/console/clinics/index.html.twig', [
            'list'           => $list,
            'status'         => $status,
            'sort'           => $sort,
            'search'         => $search,
            'todo'           => $todo,
            'sorts'          => ClinicListBuilder::SORTS,
            'features_total' => $features['total'],
            'feature_counts' => $features['counts'],
        ]);
    }
}
