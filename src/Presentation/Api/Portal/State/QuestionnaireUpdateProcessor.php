<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Context\ClientPortal\Application\Command\SubmitQuestionnaire\SubmitQuestionnaire;
use App\Context\ClientPortal\Application\Command\SubmitQuestionnaire\SubmitQuestionnaireOutcome;
use App\Context\ClientPortal\Application\Query\GetQuestionnaire\GetQuestionnaire;
use App\Context\ClientPortal\Application\Query\GetQuestionnaire\PortalQuestionnaire;
use App\Context\ClientPortal\Domain\Exception\AppointmentNotFoundException;
use App\Context\ClientPortal\Domain\Exception\InvalidPortalRequestException;
use App\Presentation\Api\Portal\Problem\PortalProblemMap;
use App\Presentation\Api\Portal\Resource\QuestionnaireUpdate;
use App\Presentation\Api\Portal\Security\PortalApiUser;
use App\Presentation\Api\Portal\Support\PortalClinicGuard;
use App\Presentation\Api\Portal\View\QuestionnaireView;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Not keyed: the answers replace the previous ones, so a retry is harmless.
 * A frozen questionnaire is a returned outcome (its lock is recorded), not
 * an exception: `422 questionnaire_locked`.
 *
 * @implements ProcessorInterface<QuestionnaireUpdate, QuestionnaireView>
 */
final readonly class QuestionnaireUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private QueryBusInterface $queryBus,
        private PortalClinicGuard $guard,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): QuestionnaireView
    {
        $user = $this->security->getUser();
        \assert($user instanceof PortalApiUser);
        $clinicId      = $uriVariables['clinicId'] ?? null;
        $appointmentId = $uriVariables['appointmentId'] ?? null;
        \assert(\is_string($clinicId) && \is_string($appointmentId));
        \assert(null !== $data->version && null !== $data->symptoms && null !== $data->ongoingTreatment && null !== $data->ongoingTreatment->has && null !== $data->temperament);

        $this->guard->require($user->id(), $clinicId);

        try {
            $outcome = $this->commandBus->dispatch(new SubmitQuestionnaire(
                $user->id(),
                $clinicId,
                $appointmentId,
                $data->version,
                $data->symptoms,
                $data->ongoingTreatment->has,
                $data->ongoingTreatment->details,
                $data->temperament,
                $data->notes,
            ));
        } catch (AppointmentNotFoundException) {
            throw PortalProblemMap::problem(PortalProblemMap::NOT_FOUND);
        } catch (InvalidPortalRequestException $invalid) {
            throw PortalProblemMap::violation($invalid->propertyPath, $invalid->getMessage());
        }

        if (SubmitQuestionnaireOutcome::LOCKED === $outcome) {
            throw PortalProblemMap::problem(PortalProblemMap::QUESTIONNAIRE_LOCKED);
        }

        $questionnaire = $this->queryBus->ask(new GetQuestionnaire($clinicId, $appointmentId, $user->id()));
        \assert($questionnaire instanceof PortalQuestionnaire);

        return QuestionnaireView::of($questionnaire);
    }
}
