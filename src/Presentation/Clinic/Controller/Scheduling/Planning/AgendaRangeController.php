<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Scheduling\Planning;

use App\Presentation\Clinic\ViewModel\Agenda\AgendaViewBuilder;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Bookings and planning blocks of a clinic-local date range, fetched by the
 * agenda when the user browses past the period it was loaded with.
 */
#[Route('/scheduling/agenda/range', name: 'clinic_scheduling_agenda_range', methods: ['GET'])]
final class AgendaRangeController extends AbstractController
{
    public function __construct(
        private readonly AgendaViewBuilder $agenda,
        private readonly CurrentClinicContextInterface $currentClinicContext,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $clinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $clinicId);

        $tz   = $this->agenda->timeZone($clinicId->toString());
        $from = $this->day($request->query->get('from'), $tz);
        $to   = $this->day($request->query->get('to'), $tz);

        if (null === $from || null === $to || $to < $from || $from->diff($to)->days > AgendaViewBuilder::MAX_RANGE_DAYS) {
            return new JsonResponse(['error' => 'invalid_range'], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse($this->agenda->range($clinicId->toString(), $from, $to, $tz));
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
