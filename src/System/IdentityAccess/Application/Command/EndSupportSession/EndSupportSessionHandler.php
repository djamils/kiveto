<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\EndSupportSession;

use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Application\Query\SupportSessionView;
use App\System\IdentityAccess\Domain\Support\SupportSessionRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class EndSupportSessionHandler
{
    public function __construct(
        private SupportSessionRepositoryInterface $sessions,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return array{session: SupportSessionView, ended: bool}|null
     */
    public function __invoke(EndSupportSession $command): ?array
    {
        $session = $this->sessions->find($command->sessionId);

        if (null === $session) {
            return null;
        }

        $ended = $session->end($this->clock->now());

        if ($ended) {
            $this->sessions->save($session);
        }

        return ['session' => SupportSessionView::of($session), 'ended' => $ended];
    }
}
