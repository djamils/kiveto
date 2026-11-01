<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Context\ClientPortal\Application\Query\GetQuestionnaire\GetQuestionnaire;
use App\Context\ClientPortal\Application\Query\GetQuestionnaire\PortalQuestionnaire;
use App\Context\ClientPortal\Domain\Exception\AppointmentNotFoundException;
use App\Presentation\Api\Portal\Problem\PortalProblemMap;
use App\Presentation\Api\Portal\Security\PortalApiUser;
use App\Presentation\Api\Portal\Support\PortalClinicGuard;
use App\Presentation\Api\Portal\View\QuestionnaireView;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<QuestionnaireView>
 */
final readonly class QuestionnaireProvider implements ProviderInterface
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private PortalClinicGuard $guard,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): QuestionnaireView
    {
        $user = $this->security->getUser();
        \assert($user instanceof PortalApiUser);
        $clinicId      = $uriVariables['clinicId'] ?? null;
        $appointmentId = $uriVariables['appointmentId'] ?? null;
        \assert(\is_string($clinicId) && \is_string($appointmentId));

        $this->guard->require($user->id(), $clinicId);

        try {
            $questionnaire = $this->queryBus->ask(new GetQuestionnaire($clinicId, $appointmentId, $user->id()));
        } catch (AppointmentNotFoundException) {
            throw PortalProblemMap::problem(PortalProblemMap::NOT_FOUND);
        }
        \assert($questionnaire instanceof PortalQuestionnaire);

        return QuestionnaireView::of($questionnaire);
    }
}
