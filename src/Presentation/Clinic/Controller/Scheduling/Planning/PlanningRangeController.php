<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Scheduling\Planning;

use App\Presentation\Clinic\ViewModel\Planning\PlanningViewBuilder;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Planning block occurrences of a clinic-local date range, fetched by the
 * planning when the user browses past the period it was loaded with.
 */
#[Route('/scheduling/planning/range', name: 'clinic_scheduling_planning_range', methods: ['GET'])]
final class PlanningRangeController extends AbstractController
{
    public function __construct(
        private readonly PlanningViewBuilder $planning,
        private readonly CurrentClinicContextInterface $currentClinicContext,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $clinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $clinicId);

        $tz   = $this->planning->timeZone($clinicId->toString());
        $from = $this->day($request->query->get('from'), $tz);
        $to   = $this->day($request->query->get('to'), $tz);

        $tooWide = null !== $from && null !== $to && $from->diff($to)->days > PlanningViewBuilder::MAX_RANGE_DAYS;
        if (null === $from || null === $to || $to < $from || $tooWide) {
            return new JsonResponse(['error' => 'invalid_range'], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse($this->planning->range($clinicId->toString(), $from, $to));
    }

    private function day(mixed $value, \DateTimeZone $tz): ?\DateTimeImmutable
    {
        if (!\is_string($value) || 1 !== preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $day = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, $tz);

        return false === $day || $day->format('Y-m-d') !== $value ? null : $day;
    }
}
