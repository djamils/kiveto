<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Audit;

use App\Presentation\Backoffice\Security\CurrentAdmin;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\Audit\Application\Command\RecordAuditEntry\RecordAuditEntry;
use App\System\Audit\Domain\ValueObject\ActorKind;
use App\System\Audit\Domain\ValueObject\AuditCategory;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Writes what the signed-in admin just did to the audit log, with his name
 * of the moment and the request's address. Called by the console
 * controllers once their command succeeded.
 */
final readonly class ConsoleAuditTrail
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private CurrentAdmin $currentAdmin,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, string> $details label => value
     */
    public function record(
        string $action,
        AuditCategory $category,
        string $summary,
        ?string $clinicId = null,
        ?string $clinicName = null,
        ?string $targetUserId = null,
        array $details = [],
    ): void {
        $admin = $this->currentAdmin->get();

        $this->commandBus->dispatch(new RecordAuditEntry(
            actorKind: ActorKind::KIVETO,
            actorId: $admin->id,
            actorName: $admin->name,
            action: $action,
            category: $category,
            summary: $summary,
            clinicId: $clinicId,
            clinicName: $clinicName,
            targetUserId: $targetUserId,
            ipAddress: $this->requestStack->getCurrentRequest()?->getClientIp(),
            details: self::details($details),
        ));
    }

    /**
     * @param array<string, string> $details
     *
     * @return list<array{label: string, value: string}>
     */
    public static function details(array $details): array
    {
        $lines = [];
        foreach ($details as $label => $value) {
            $lines[] = ['label' => (string) $label, 'value' => $value];
        }

        return $lines;
    }
}
