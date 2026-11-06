<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Feature;

use App\Context\Clinic\Application\Query\Console\ConsoleClinicRow;
use App\Presentation\Backoffice\Audit\ConsoleAuditTrail;
use App\Presentation\Backoffice\Security\CurrentAdmin;
use App\Presentation\Backoffice\ViewModel\Clinic\ClinicLifecycle;
use App\Presentation\Backoffice\ViewModel\ConsoleDirectory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\System\Audit\Application\Query\SearchAuditLog\AuditLogPage;
use App\System\Audit\Application\Query\SearchAuditLog\SearchAuditLog;
use App\System\Audit\Domain\ValueObject\AuditCategory;
use App\System\FeatureCatalog\Application\Command\ApplyStartingFeatures\ApplyStartingFeatures;
use App\System\FeatureCatalog\Application\Command\ChangeFeatureSettings\ChangeFeatureSettings;
use App\System\FeatureCatalog\Application\Command\SetClinicFeature\SetClinicFeature;
use App\System\FeatureCatalog\Application\Query\ClinicFeatureView;
use App\System\FeatureCatalog\Application\Query\FeatureView;
use App\System\FeatureCatalog\Application\Query\ListClinicFeatures\ListClinicFeatures;
use App\System\FeatureCatalog\Application\Query\ListFeatures\ListFeatures;

/**
 * F1–F3 and the features of a clinic (B5, C1, B1): what the catalogue says,
 * what the console changes, each change audited.
 */
final readonly class ConsoleFeatures
{
    public const array BADGES = ['available' => 'is-green', 'beta' => 'is-purple', 'internal' => 'is-outline', 'upcoming' => 'is-outline'];

    public function __construct(
        private QueryBusInterface $queryBus,
        private CommandBusInterface $commandBus,
        private ConsoleDirectory $directory,
        private ConsoleAuditTrail $auditTrail,
        private CurrentAdmin $currentAdmin,
    ) {
    }

    /**
     * Every feature, counted over the clinics the console lists.
     *
     * @return list<FeatureView>
     */
    public function list(): array
    {
        return $this->features(array_map(static fn (ConsoleClinicRow $c): string => $c->id, $this->directory->clinics()));
    }

    public function find(string $code): ?FeatureView
    {
        foreach ($this->list() as $feature) {
            if ($feature->code === $code) {
                return $feature;
            }
        }

        return null;
    }

    /**
     * F2 — each clinic with its state for this feature, and the history.
     *
     * @return array{feature: FeatureView, clinics: list<array{clinic: ConsoleClinicRow, lifecycle: ClinicLifecycle, state: ClinicFeatureView}>, history: list<mixed>, missing: list<array{clinic: ConsoleClinicRow, lifecycle: ClinicLifecycle}>}|null
     */
    public function detail(string $code): ?array
    {
        $feature = $this->find($code);

        if (null === $feature) {
            return null;
        }

        $clinics = $this->directory->clinics();
        $states  = $this->states(array_map(static fn (ConsoleClinicRow $c): string => $c->id, $clinics))[$code] ?? [];
        $rows    = [];
        $missing = [];
        foreach ($clinics as $clinic) {
            $state = $states[$clinic->id] ?? null;

            if (null === $state) {
                continue;
            }

            $lifecycle = ClinicLifecycle::of($clinic->isSuspended(), null !== $clinic->onboardedAt, $clinic->plan);
            $rows[]    = ['clinic' => $clinic, 'lifecycle' => $lifecycle, 'state' => $state];

            if (!$state->enabled && !$clinic->isSuspended()) {
                $missing[] = ['clinic' => $clinic, 'lifecycle' => $lifecycle];
            }
        }

        $history = $this->queryBus->ask(new SearchAuditLog(perPage: 8, category: AuditCategory::FEATURES, text: '« ' . $feature->name . ' »'));
        \assert($history instanceof AuditLogPage);

        return ['feature' => $feature, 'clinics' => $rows, 'history' => $history->items, 'missing' => $missing];
    }

    /**
     * The features of one clinic, for its sheet.
     *
     * @return list<array{feature: FeatureView, state: ClinicFeatureView}>
     */
    public function ofClinic(string $clinicId): array
    {
        $states = $this->states([$clinicId]);
        $cards  = [];
        foreach ($this->features([$clinicId]) as $feature) {
            $state = $states[$feature->code][$clinicId] ?? null;

            if (null !== $state) {
                $cards[] = ['feature' => $feature, 'state' => $state];
            }
        }

        return $cards;
    }

    /**
     * clinicId → how many features it has on, for the clinics list.
     *
     * @param list<string> $clinicIds
     *
     * @return array{total: int, counts: array<string, int>}
     */
    public function counts(array $clinicIds): array
    {
        $features = array_values(array_filter($this->features([]), static fn (FeatureView $f): bool => !$f->isUpcoming()));
        $states   = $this->states($clinicIds);
        $counts   = array_fill_keys($clinicIds, 0);
        foreach ($features as $feature) {
            foreach ($states[$feature->code] ?? [] as $clinicId => $state) {
                $counts[$clinicId] += $state->enabled ? 1 : 0;
            }
        }

        return ['total' => \count($features), 'counts' => $counts];
    }

    /**
     * B5 — the features offered at the third step of a new clinic.
     *
     * @return list<array{code: string, name: string, description: string, defaultOn: bool, upcoming: bool, badge: ?array{label: string, class: string}}>
     */
    public function starting(): array
    {
        return array_map(static fn (FeatureView $f): array => [
            'code'        => $f->code,
            'name'        => $f->name,
            'description' => $f->description,
            'defaultOn'   => $f->defaultOn,
            'upcoming'    => $f->isUpcoming(),
            'badge'       => 'available' !== $f->status ? ['label' => $f->statusLabel, 'class' => self::BADGES[$f->status] ?? 'is-outline'] : null,
        ], $this->features([]));
    }

    /**
     * @param list<string> $codes
     */
    public function applyStarting(string $clinicId, array $codes): void
    {
        $admin = $this->currentAdmin->get();
        $this->commandBus->dispatch(new ApplyStartingFeatures($clinicId, $codes, $admin->id, $admin->name));
    }

    public function toggle(ConsoleClinicRow $clinic, FeatureView $feature, bool $enabled): bool
    {
        $admin   = $this->currentAdmin->get();
        $changed = $this->commandBus->dispatch(new SetClinicFeature($clinic->id, $feature->code, $enabled, $admin->id, $admin->name));

        if (true === $changed) {
            $this->auditTrail->record(
                $enabled ? 'feature.enabled' : 'feature.disabled',
                AuditCategory::FEATURES,
                \sprintf('Fonctionnalité « %s » %s', $feature->name, $enabled ? 'activée' : 'désactivée'),
                clinicId: $clinic->id,
                clinicName: $clinic->name,
                details: ['Code' => $feature->code],
            );
        }

        return true === $changed;
    }

    /**
     * F3 — on for every clinic not suspended that does not have it yet.
     */
    public function enableForAll(FeatureView $feature): int
    {
        $detail = $this->detail($feature->code);
        \assert(null !== $detail);
        $admin = $this->currentAdmin->get();
        $count = 0;
        foreach ($detail['missing'] as $row) {
            $this->commandBus->dispatch(new SetClinicFeature($row['clinic']->id, $feature->code, true, $admin->id, $admin->name));
            ++$count;
        }

        $this->auditTrail->record(
            'feature.enabled_for_all',
            AuditCategory::FEATURES,
            \sprintf('Fonctionnalité « %s » activée pour %d clinique%s', $feature->name, $count, $count > 1 ? 's' : ''),
            details: ['Code' => $feature->code, 'Cliniques' => implode(', ', array_map(static fn (array $row): string => $row['clinic']->name, $detail['missing']))],
        );

        return $count;
    }

    /**
     * @throws \DomainException a status refused
     *
     * @return list<string> what changed
     */
    public function changeSettings(FeatureView $feature, string $status, bool $defaultOn): array
    {
        $changes = $this->commandBus->dispatch(new ChangeFeatureSettings($feature->code, $status, $defaultOn));
        $changes = \is_array($changes) ? array_values(array_filter($changes, is_string(...))) : [];

        if ([] !== $changes) {
            $this->auditTrail->record(
                'feature.settings_changed',
                AuditCategory::FEATURES,
                \sprintf('Fonctionnalité « %s » : %s', $feature->name, mb_strtolower(implode(', ', $changes))),
                details: ['Code' => $feature->code],
            );
        }

        return $changes;
    }

    /**
     * @param list<string> $clinicIds
     *
     * @return list<FeatureView>
     */
    private function features(array $clinicIds): array
    {
        $answer = $this->queryBus->ask(new ListFeatures($clinicIds));

        return \is_array($answer) ? array_values(array_filter($answer, static fn (mixed $f): bool => $f instanceof FeatureView)) : [];
    }

    /**
     * @param list<string> $clinicIds
     *
     * @return array<string, array<string, ClinicFeatureView>>
     */
    private function states(array $clinicIds): array
    {
        $answer = $this->queryBus->ask(new ListClinicFeatures($clinicIds));
        $states = [];
        foreach (\is_array($answer) ? $answer : [] as $code => $byClinic) {
            foreach (\is_array($byClinic) ? $byClinic : [] as $clinicId => $state) {
                if ($state instanceof ClinicFeatureView) {
                    $states[(string) $code][(string) $clinicId] = $state;
                }
            }
        }

        return $states;
    }
}
