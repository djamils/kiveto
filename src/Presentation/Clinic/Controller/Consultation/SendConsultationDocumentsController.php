<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Consultation;

use App\Context\Consultation\Application\Command\SendConsultationDocuments\ConsultationDocumentsQueued;
use App\Context\Consultation\Application\Command\SendConsultationDocuments\SendConsultationDocuments;
use App\Context\Consultation\Application\Service\Documents\ConsultationDocumentsNotSendable;
use App\Presentation\Clinic\Controller\Consultation\Record\CockpitEndpoint;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Envoyer par e-mail » from the documents panel of a closed consultation:
 * the ticked documents (`report`, `prescription:<id>`, `invoice`) are
 * checked and queued, and the toast names the recipient at once (AC 27).
 */
final class SendConsultationDocumentsController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly CockpitEndpoint $endpoint,
    ) {
    }

    #[Route('/clinic/consultations/{id}/documents/send', name: 'clinic_consultation_send_documents', methods: ['POST'])]
    public function __invoke(string $id, Request $request): JsonResponse
    {
        if (!$this->endpoint->isCsrfValid($request)) {
            return new JsonResponse(['success' => false, 'error' => 'Session expirée, rechargez la page.'], JsonResponse::HTTP_FORBIDDEN);
        }

        $clinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $clinicId);
        $user = $this->getUser();

        try {
            $queued = $this->commandBus->dispatch(new SendConsultationDocuments(
                clinicId: $clinicId->toString(),
                consultationId: $id,
                documents: array_values(array_filter($request->request->all('documents'), \is_string(...))),
                actorUserId: $user instanceof SecurityUser ? $user->id() : null,
            ));
        } catch (ConsultationDocumentsNotSendable $exception) {
            return new JsonResponse(['success' => false, 'error' => $exception->getMessage()], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\LogicException) { // unknown to the clinic (DomainException) or malformed id (InvalidArgumentException)
            return new JsonResponse(['success' => false, 'error' => 'Consultation introuvable.'], JsonResponse::HTTP_NOT_FOUND);
        }

        \assert($queued instanceof ConsultationDocumentsQueued);
        $plural = $queued->documentCount > 1 ? 's' : '';

        return new JsonResponse([
            'success' => true,
            'message' => \sprintf('%d document%s envoyé%s par e-mail à %s (%s)', $queued->documentCount, $plural, $plural, $queued->recipientName, $queued->recipientEmail),
        ]);
    }
}
