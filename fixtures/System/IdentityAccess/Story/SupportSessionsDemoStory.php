<?php

declare(strict_types=1);

namespace App\Fixtures\System\IdentityAccess\Story;

use App\Fixtures\Context\Clinic\Story\ClinicDataStory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\Audit\Application\Command\RecordAuditEntry\RecordAuditEntry;
use App\System\Audit\Domain\ValueObject\ActorKind;
use App\System\Audit\Domain\ValueObject\AuditCategory;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Story;

/**
 * Two past support sessions in Paris (C1 « Sessions support », the audit
 * log): one read-only, one allowed to write. Needs the console admin of the
 * platform dataset; skipped without it.
 */
final class SupportSessionsDemoStory extends Story
{
    public function __construct(
        private readonly Connection $connection,
        private readonly CommandBusInterface $commandBus,
    ) {
    }

    public function build(): void
    {
        $adminId = $this->connection->fetchOne("SELECT BIN_TO_UUID(id) FROM identity_access__users WHERE email = 'admin@kiveto.local'");
        $members = $this->connection->fetchAllAssociative(
            "SELECT BIN_TO_UUID(m.user_id) AS user_id, CONCAT(p.first_name, ' ', p.last_name) AS name FROM clinic__clinic_memberships m"
            . ' JOIN clinic__staff_profiles p ON p.membership_id = m.id JOIN identity_access__users u ON u.id = m.user_id'
            . " WHERE m.clinic_id = UUID_TO_BIN(?) AND m.status = 'ACTIVE' AND u.status = 'ACTIVE' ORDER BY m.created_at_utc LIMIT 2",
            [ClinicDataStory::INDEPENDENT_CLINIC_ID],
        );

        if (!\is_string($adminId) || [] === $members) {
            return;
        }

        $sessions = [
            ['-8 days 10:12', 12, 'read', 'Agenda en double sur le créneau de 14 h', 9, 0],
            ['-16 days 15:40', 25, 'write', 'Import du stock initial', 31, 6],
        ];

        foreach ($sessions as $i => [$when, $minutes, $mode, $reason, $pages, $writes]) {
            $member  = $members[$i % \count($members)];
            $userId  = \is_string($member['user_id'] ?? null) ? $member['user_id'] : '';
            $name    = \is_string($member['name'] ?? null) ? $member['name'] : '';
            $started = new \DateTimeImmutable($when, new \DateTimeZone('Europe/Paris'));
            $ended   = $started->modify(\sprintf('+%d minutes', $minutes));
            $utc     = static fn (\DateTimeImmutable $at): string => $at->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');

            $this->connection->insert('identity_access__support_sessions', [
                'id'           => Uuid::v7()->toBinary(),
                'clinic_id'    => Uuid::fromString(ClinicDataStory::INDEPENDENT_CLINIC_ID)->toBinary(),
                'user_id'      => Uuid::fromString($userId)->toBinary(),
                'admin_id'     => Uuid::fromString($adminId)->toBinary(),
                'admin_name'   => 'Djamil Soidiki',
                'reason'       => $reason,
                'mode'         => $mode,
                'started_at'   => $utc($started),
                'expires_at'   => $utc($started->modify('+30 minutes')),
                'handoff_hash' => hash('sha256', 'demo-support-' . $i),
                'claimed_at'   => $utc($started),
                'ended_at'     => $utc($ended),
                'pages'        => $pages,
                'writes'       => $writes,
            ]);

            foreach ([['support.session_started', \sprintf('Session support démarrée en tant que %s', $name), $started], ['support.session_ended', \sprintf('Session support terminée · %d min', $minutes), $ended]] as [$action, $summary, $at]) {
                $this->commandBus->dispatch(new RecordAuditEntry(
                    actorKind: ActorKind::KIVETO,
                    actorId: $adminId,
                    actorName: 'Djamil Soidiki',
                    action: $action,
                    category: AuditCategory::SUPPORT,
                    summary: $summary,
                    clinicId: ClinicDataStory::INDEPENDENT_CLINIC_ID,
                    clinicName: 'Clinique Vétérinaire de Paris',
                    targetUserId: $userId,
                    details: [['label' => 'Motif', 'value' => $reason], ['label' => 'Mode', 'value' => 'read' === $mode ? 'Lecture seule' : 'Lecture et écriture']],
                    occurredAt: $at,
                ));
            }
        }
    }
}
