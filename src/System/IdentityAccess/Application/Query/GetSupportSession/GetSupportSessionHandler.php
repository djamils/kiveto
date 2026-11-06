<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Query\GetSupportSession;

use App\System\IdentityAccess\Application\Query\SupportSessionView;
use App\System\IdentityAccess\Domain\Support\SupportSessionRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetSupportSessionHandler
{
    public function __construct(
        private SupportSessionRepositoryInterface $sessions,
    ) {
    }

    public function __invoke(GetSupportSession $query): ?SupportSessionView
    {
        $session = $this->sessions->find($query->sessionId);

        return null !== $session ? SupportSessionView::of($session) : null;
    }
}
