<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\ClosePortalRequestsForSubject;

use App\Context\ClientPortal\Application\Service\PortalRequestCloser;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ClosePortalRequestsForSubjectHandler
{
    public function __construct(private PortalRequestCloser $closer)
    {
    }

    public function __invoke(ClosePortalRequestsForSubject $command): int
    {
        return $this->closer->close($command->subject, $command->subjectId, $command->reason, $command->clinicId);
    }
}
