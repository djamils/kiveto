<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\Clinic;

use App\Context\Clinic\Domain\Account\ClinicPlan;
use App\Context\Clinic\Domain\Account\ParallelSoftware;
use App\Context\Clinic\Domain\Account\SuspensionReason;
use App\Presentation\Backoffice\Controller\Account\ShowAccountController;
use App\Presentation\Backoffice\Feature\ConsoleFeatures;
use App\Presentation\Backoffice\ViewModel\Clinic\ClinicSheetBuilder;
use App\Presentation\Backoffice\ViewModel\ConsoleDirectory;
use App\Shared\Application\Bus\QueryBusInterface;
use App\System\IdentityAccess\Application\Query\GetSupportSession\GetSupportSession;
use App\System\IdentityAccess\Application\Query\SupportSessionView;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * C1 / C2 / C3 — a clinic's sheet, with its edit panel, its invitation and
 * suspension windows.
 */
final class ShowClinicController extends AbstractController
{
    public const string UUID = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

    public function __construct(
        private readonly ClinicSheetBuilder $builder,
        private readonly ConsoleFeatures $features,
        private readonly QueryBusInterface $queryBus,
    ) {
    }

    #[Route(path: '/clinics/{id}', name: 'backoffice_clinic_show', requirements: ['id' => self::UUID], methods: ['GET'])]
    public function __invoke(Request $request, string $id): Response
    {
        $sheet = $this->builder->build($id);

        if (null === $sheet) {
            throw $this->createNotFoundException('Clinique introuvable.');
        }

        $dialog  = $request->query->getString('dialog');
        $dialog  = \in_array($dialog, ['edit', 'invite', 'suspend'], true) ? $dialog : ($request->query->getBoolean('support') ? 'support' : '');
        $session = $request->getSession();
        // The refusal of a window is shown inside it, not behind it.
        $error = '' !== $dialog && $session instanceof FlashBagAwareSessionInterface ? ($session->getFlashBag()->get('error')[0] ?? null) : null;

        return $this->render('backoffice/console/clinics/show.html.twig', [
            'sheet'       => $sheet,
            'open_dialog' => match (true) {
                '' !== $dialog => 'dialog-' . $dialog,
                default        => '',
            },
            $dialog . '_error' => $error,
            'time_zones'       => ShowAccountController::TIME_ZONES,
            'plans'            => array_combine(array_map(static fn (ClinicPlan $p): string => $p->value, ClinicPlan::cases()), array_map(static fn (ClinicPlan $p): string => $p->label(), ClinicPlan::cases())),
            'softwares'        => array_combine(array_map(static fn (ParallelSoftware $s): string => $s->value, ParallelSoftware::cases()), array_map(static fn (ParallelSoftware $s): string => $s->label(), ParallelSoftware::cases())),
            'reasons'          => array_combine(array_map(static fn (SuspensionReason $r): string => $r->value, SuspensionReason::cases()), array_map(static fn (SuspensionReason $r): string => $r->label(), SuspensionReason::cases())),
            'roles'            => ['VETERINARY' => ConsoleDirectory::roleLabel('VETERINARY'), 'VETERINARY_ASSISTANT' => 'ASV', 'RECEPTIONIST' => 'Accueil', 'MANAGER' => 'Gérant'],
            'price_per_vet'    => $this->builder->pricePerVet(),
            'features'         => $this->features->ofClinic($id),
            'support_summary'  => $this->summary($request, $id, $sheet),
            'feature_badges'   => ConsoleFeatures::BADGES,
        ]);
    }

    /**
     * D3 — back from a support session (`?support_ended=<id>`): what it was.
     *
     * @param array<string, mixed> $sheet
     *
     * @return array{name: string, view: SupportSessionView}|null
     */
    private function summary(Request $request, string $clinicId, array $sheet): ?array
    {
        $sessionId = $request->query->getString('support_ended');

        if (!Uuid::isValid($sessionId)) {
            return null;
        }

        $view = $this->queryBus->ask(new GetSupportSession($sessionId));

        if (!$view instanceof SupportSessionView || $view->clinicId !== $clinicId) {
            return null;
        }

        $name = $view->userId;
        foreach (\is_array($sheet['team'] ?? null) ? $sheet['team'] : [] as $member) {
            if (\is_array($member) && ($member['userId'] ?? null) === $view->userId && \is_string($member['name'] ?? null)) {
                $name = $member['name'];
            }
        }

        return ['name' => $name, 'view' => $view];
    }
}
