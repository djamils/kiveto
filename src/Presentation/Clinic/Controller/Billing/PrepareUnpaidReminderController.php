<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Billing;

use App\Context\Billing\Application\Query\PrepareUnpaidReminder\PrepareUnpaidReminder;
use App\Context\Billing\Application\Query\PrepareUnpaidReminder\UnpaidReminderPreview;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The confirmation of a bulk reminder (« N clients · environ S SMS »): what
 * « Relancer » would do for a selection (`ids[]`) or every unpaid invoice
 * (`all=1`), nothing sent.
 */
#[Route(path: '/billing/reminders/preview', name: 'clinic_invoices_reminder_preview', methods: ['GET'])]
final class PrepareUnpaidReminderController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $ids = array_values(array_filter($request->query->all('ids'), \is_string(...)));

        $preview = $this->queryBus->ask(new PrepareUnpaidReminder(
            $currentClinicId->toString(),
            $request->query->getBoolean('all') ? null : $ids,
        ));
        \assert($preview instanceof UnpaidReminderPreview);

        return new JsonResponse([
            'invoices' => \count($preview->invoiceIds),
            'clients'  => $preview->clientCount,
            'sms'      => $preview->smsCount,
            'segments' => $preview->smsSegments,
            'skipped'  => $preview->skippedCount,
        ]);
    }
}
