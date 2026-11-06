<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\Audit\Infrastructure\Persistence\Doctrine\Repository;

use App\System\Audit\Application\Port\AuditLogCriteria;
use App\System\Audit\Application\Port\AuditLogReadRepositoryInterface;
use App\System\Audit\Domain\AuditEntry;
use App\System\Audit\Domain\Repository\AuditEntryRepositoryInterface;
use App\System\Audit\Domain\ValueObject\ActorKind;
use App\System\Audit\Domain\ValueObject\AuditAction;
use App\System\Audit\Domain\ValueObject\AuditActor;
use App\System\Audit\Domain\ValueObject\AuditCategory;
use App\System\Audit\Domain\ValueObject\AuditDetail;
use App\System\Audit\Domain\ValueObject\AuditEntryId;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The append-only log written by DbalAuditEntryRepository and read back by
 * DbalAuditLogReadRepository: every column, the filters, the order.
 */
final class DbalAuditLogRepositoriesTest extends KernelTestCase
{
    private const string ADMIN  = '01960000-0000-7000-8000-000000000a01';
    private const string VET    = '01960000-0000-7000-8000-000000000a02';
    private const string PRADO  = '01960000-0000-7000-8000-000000000c01';
    private const string CASTEL = '01960000-0000-7000-8000-000000000c02';
    private const string TARGET = '01960000-0000-7000-8000-000000000b01';

    private AuditEntryRepositoryInterface $entries;
    private AuditLogReadRepositoryInterface $log;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $connection = $container->get(Connection::class);
        \assert($connection instanceof Connection);
        // DAMA rolls this back: each test reads only its own entries.
        $connection->executeStatement('DELETE FROM audit__entries');

        $entries = $container->get(AuditEntryRepositoryInterface::class);
        \assert($entries instanceof AuditEntryRepositoryInterface);
        $this->entries = $entries;

        $log = $container->get(AuditLogReadRepositoryInterface::class);
        \assert($log instanceof AuditLogReadRepositoryInterface);
        $this->log = $log;
    }

    public function testAnEntryIsReadBackWithEveryColumn(): void
    {
        $this->entries->append(AuditEntry::record(
            id: AuditEntryId::fromString('01960000-0000-7000-8000-000000000e01'),
            occurredAt: new \DateTimeImmutable('2026-11-01 03:32:00', new \DateTimeZone('Europe/Paris')),
            actor: AuditActor::kiveto(self::ADMIN, 'Djamil Soidiki'),
            action: AuditAction::fromString('support.session_ended'),
            category: AuditCategory::SUPPORT,
            summary: 'Session support terminée',
            clinicId: self::PRADO,
            clinicName: 'Clinique du Prado',
            targetUserId: self::TARGET,
            ipAddress: '91.12.34.12',
            details: [AuditDetail::of('Utilisateur ciblé', 'Dr Claire Martin'), AuditDetail::of('Pages consultées', '14 · aucune écriture')],
        ));

        $views = $this->log->search(new AuditLogCriteria(), 0, 10);

        self::assertCount(1, $views);
        $view = $views[0];
        self::assertSame('01960000-0000-7000-8000-000000000e01', $view->id);
        self::assertSame('2026-11-01T02:32:00+00:00', $view->occurredAt->format(\DATE_ATOM), 'stored in UTC');
        self::assertSame('kiveto', $view->actorKind);
        self::assertSame(self::ADMIN, $view->actorId);
        self::assertSame('Djamil Soidiki', $view->actorName);
        self::assertSame('support.session_ended', $view->action);
        self::assertSame('support', $view->category);
        self::assertSame('Session support terminée', $view->summary);
        self::assertSame(self::PRADO, $view->clinicId);
        self::assertSame('Clinique du Prado', $view->clinicName);
        self::assertSame(self::TARGET, $view->targetUserId);
        self::assertSame('91.12.34.12', $view->ipAddress);
        self::assertCount(2, $view->details);
        self::assertSame('Utilisateur ciblé', $view->details[0]->label);
        self::assertSame('Dr Claire Martin', $view->details[0]->value);
        self::assertSame('14 · aucune écriture', $view->details[1]->value);
    }

    public function testAnAnonymousEntryHasNoActorClinicNorDetail(): void
    {
        $this->append('e01', '2026-10-26 23:41', AuditActor::unknown(), 'auth.login_failed', AuditCategory::ACCESS, 'Échec de connexion');

        $view = $this->log->search(new AuditLogCriteria(), 0, 10)[0];

        self::assertSame('unknown', $view->actorKind);
        self::assertNull($view->actorId);
        self::assertNull($view->actorName);
        self::assertNull($view->clinicId);
        self::assertNull($view->clinicName);
        self::assertNull($view->targetUserId);
        self::assertNull($view->ipAddress);
        self::assertSame([], $view->details);
    }

    public function testTheFiltersNarrowTheLogNewestFirst(): void
    {
        $this->seed();

        self::assertSame(['e05', 'e04', 'e03', 'e02', 'e01'], $this->ids(new AuditLogCriteria()));
        self::assertSame(5, $this->log->count(new AuditLogCriteria()));

        self::assertSame(['e04', 'e03'], $this->ids(new AuditLogCriteria(category: AuditCategory::SUPPORT)));
        self::assertSame(['e04', 'e03', 'e02'], $this->ids(new AuditLogCriteria(clinicId: self::PRADO)));
        self::assertSame(['e05'], $this->ids(new AuditLogCriteria(actorKind: ActorKind::STAFF)));
        self::assertSame(['e05', 'e04'], $this->ids(new AuditLogCriteria(since: new \DateTimeImmutable('2026-11-01 00:00:00'))));
        self::assertSame(2, $this->log->count(new AuditLogCriteria(since: new \DateTimeImmutable('2026-11-01 00:00:00'))));

        // The text matches the summary, the code, the actor's and the clinic's names.
        self::assertSame(['e05'], $this->ids(new AuditLogCriteria(text: 'castellane')));
        self::assertSame(['e04', 'e03'], $this->ids(new AuditLogCriteria(text: 'session_')));
        self::assertSame(['e05'], $this->ids(new AuditLogCriteria(text: 'Lucie')));
        self::assertSame(['e02'], $this->ids(new AuditLogCriteria(text: 'Hospitalisation')));
        self::assertSame([], $this->ids(new AuditLogCriteria(text: '%')), 'a wildcard is searched literally');

        self::assertSame(['e04'], $this->ids(new AuditLogCriteria(category: AuditCategory::SUPPORT, since: new \DateTimeImmutable('2026-11-01 00:00:00'))));
    }

    public function testAPageIsReadWithItsOffset(): void
    {
        $this->seed();

        $page = $this->log->search(new AuditLogCriteria(), 2, 2);

        self::assertSame(['e03', 'e02'], array_map(static fn ($v): string => substr($v->id, -3), $page));
    }

    public function testTheClinicActivityAndTheUserHistory(): void
    {
        $this->seed();

        self::assertSame(['e04', 'e03'], array_map(static fn ($v): string => substr($v->id, -3), $this->log->forClinic(self::PRADO, 2)));
        // Written by the account (e05) or about it (e03, e04).
        self::assertSame(['e05', 'e04', 'e03'], array_map(static fn ($v): string => substr($v->id, -3), $this->log->forUser(self::VET, 10)));
        self::assertSame(['e05', 'e04', 'e03'], $this->ids(new AuditLogCriteria(userId: self::VET)));
        self::assertSame(['e01'], $this->ids(new AuditLogCriteria(actorKind: ActorKind::SYSTEM)));
    }

    private function seed(): void
    {
        $admin = AuditActor::kiveto(self::ADMIN, 'Djamil Soidiki');

        $this->append('e01', '2026-10-20 07:12', AuditActor::system('Import ANMV'), 'registry.import_succeeded', AuditCategory::REGISTRY, 'Registre mis à jour : +12');
        $this->append('e02', '2026-10-27 06:58', $admin, 'feature.enabled', AuditCategory::FEATURES, 'Fonctionnalité « Hospitalisation » activée', self::PRADO, 'Clinique du Prado');
        $this->append('e03', '2026-10-29 10:40', $admin, 'support.session_started', AuditCategory::SUPPORT, 'Session support démarrée', self::PRADO, 'Clinique du Prado', self::VET);
        $this->append('e04', '2026-11-01 02:14', $admin, 'support.session_ended', AuditCategory::SUPPORT, 'Session support terminée', self::PRADO, 'Clinique du Prado', self::VET);
        $this->append('e05', '2026-11-01 21:05', AuditActor::staff(self::VET, 'Dr Lucie Vidal'), 'user.activated', AuditCategory::USERS, 'Invitation acceptée', self::CASTEL, 'Cabinet Castellane');
    }

    private function append(
        string $suffix,
        string $at,
        AuditActor $actor,
        string $action,
        AuditCategory $category,
        string $summary,
        ?string $clinicId = null,
        ?string $clinicName = null,
        ?string $target = null,
    ): void {
        $this->entries->append(AuditEntry::record(
            id: AuditEntryId::fromString('01960000-0000-7000-8000-000000000' . $suffix),
            occurredAt: new \DateTimeImmutable($at, new \DateTimeZone('UTC')),
            actor: $actor,
            action: AuditAction::fromString($action),
            category: $category,
            summary: $summary,
            clinicId: $clinicId,
            clinicName: $clinicName,
            targetUserId: $target,
        ));
    }

    /**
     * @return list<string>
     */
    private function ids(AuditLogCriteria $criteria): array
    {
        return array_map(static fn ($view): string => substr($view->id, -3), $this->log->search($criteria, 0, 50));
    }
}
