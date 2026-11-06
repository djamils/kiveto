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
use App\System\FeatureCatalog\Application\Command\CutFeature\CutFeature;
use App\System\FeatureCatalog\Application\Command\RestoreFeature\RestoreFeature;
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

    public const array AUDIENCES = ['all' => 'Toutes les cliniques', 'pilot' => 'Cliniques pilotes', 'paid' => 'Cliniques payantes'];

    public const array STATES = ['all', 'on', 'off'];

    public const array PLANS = ['all', 'pilot', 'paid'];

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
     * F2 — each clinic with its state for this feature (`all`), those the
     * filters keep (`clinics`: a name or tenant, on or off, a plan), the
     * counts of the state filter, and the history.
     *
     * @return array{
     *     feature: FeatureView,
     *     all: list<array{clinic: ConsoleClinicRow, lifecycle: ClinicLifecycle, state: ClinicFeatureView}>,
     *     clinics: list<array{clinic: ConsoleClinicRow, lifecycle: ClinicLifecycle, state: ClinicFeatureView}>,
     *     counts: array<string, int>,
     *     history: list<mixed>,
     *     missing: list<array{clinic: ConsoleClinicRow, lifecycle: ClinicLifecycle}>
     * }|null
     */
    public function detail(string $code, string $search = '', string $state = 'all', string $plan = 'all'): ?array
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
            $one = $states[$clinic->id] ?? null;

            if (null === $one) {
                continue;
            }

            $lifecycle = ClinicLifecycle::of($clinic->isSuspended(), null !== $clinic->onboardedAt, $clinic->plan);
            $rows[]    = ['clinic' => $clinic, 'lifecycle' => $lifecycle, 'state' => $one];

            if (!$one->enabled && $one->own && !$clinic->isSuspended()) {
                $missing[] = ['clinic' => $clinic, 'lifecycle' => $lifecycle];
            }
        }

        $byPlan = array_values(array_filter($rows, static fn (array $row): bool => self::matches($row, $search, 'all', $plan)));
        $counts = [
            'all' => \count($byPlan),
            'on'  => \count(array_filter($byPlan, static fn (array $row): bool => $row['state']->enabled)),
            'off' => \count(array_filter($byPlan, static fn (array $row): bool => !$row['state']->enabled)),
        ];

        $history = $this->queryBus->ask(new SearchAuditLog(perPage: 8, category: AuditCategory::FEATURES, text: '« ' . $feature->name . ' »'));
        \assert($history instanceof AuditLogPage);

        return [
            'feature' => $feature,
            'all'     => $rows,
            'clinics' => array_values(array_filter($byPlan, static fn (array $row): bool => self::matches($row, '', $state, 'all'))),
            'counts'  => $counts,
            'history' => $history->items,
            'missing' => $missing,
        ];
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
     * B5 — the features offered at the third step of a new clinic, with
     * the plan each one is kept for.
     *
     * @return list<array{code: string, name: string, description: string, defaultOn: bool, upcoming: bool, audience: string, badge: ?array{label: string, class: string}}>
     */
    public function starting(): array
    {
        return array_map(static fn (FeatureView $f): array => [
            'code'        => $f->code,
            'name'        => $f->name,
            'description' => $f->description,
            'defaultOn'   => $f->defaultOn && !$f->isCut(),
            'upcoming'    => $f->isUpcoming() || $f->isCut(),
            'audience'    => $f->audience,
            'badge'       => match (true) {
                $f->isCut()                => ['label' => 'Coupée partout', 'class' => 'is-red'],
                'available' !== $f->status => ['label' => $f->statusLabel, 'class' => self::BADGES[$f->status] ?? 'is-outline'],
                default                    => null,
            },
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

        return $this->switchMany($feature, true, array_map(static fn (array $row): ConsoleClinicRow => $row['clinic'], $detail['missing']), 'feature.enabled_for_all');
    }

    /**
     * F2 — on (or off) for the clinics the filters keep, whose switch decides
     * and which are not suspended.
     */
    public function switchShown(FeatureView $feature, bool $enabled, string $search, string $state, string $plan): int
    {
        $detail = $this->detail($feature->code, $search, $state, $plan);
        \assert(null !== $detail);
        $clinics = [];
        foreach ($detail['clinics'] as $row) {
            if ($row['state']->own && $row['state']->enabled !== $enabled && !$row['clinic']->isSuspended()) {
                $clinics[] = $row['clinic'];
            }
        }

        return $this->switchMany($feature, $enabled, $clinics, $enabled ? 'feature.enabled_for_many' : 'feature.disabled_for_many');
    }

    /**
     * The kill switch, audited with its reason.
     *
     * @throws \DomainException an upcoming feature, one already cut off, no reason
     */
    public function cut(FeatureView $feature, string $reason): void
    {
        $admin = $this->currentAdmin->get();
        $this->commandBus->dispatch(new CutFeature($feature->code, $reason, $admin->name));
        $this->auditTrail->record(
            'feature.cut_off',
            AuditCategory::FEATURES,
            \sprintf('Fonctionnalité « %s » coupée partout', $feature->name),
            details: ['Code' => $feature->code, 'Motif' => trim($reason), 'Cliniques qui l’avaient' => (string) $feature->enabledCount],
        );
    }

    /**
     * @throws \DomainException a feature that is not cut off
     */
    public function restore(FeatureView $feature): void
    {
        $this->commandBus->dispatch(new RestoreFeature($feature->code));
        $this->auditTrail->record(
            'feature.restored',
            AuditCategory::FEATURES,
            \sprintf('Fonctionnalité « %s » remise en service', $feature->name),
            details: ['Code' => $feature->code, 'Coupée depuis' => $feature->cutAt?->format('d/m/Y H:i') . ' UTC', 'Motif' => (string) $feature->cutReason],
        );
    }

    /**
     * @throws \DomainException a status or an audience refused
     *
     * @return list<string> what changed
     */
    public function changeSettings(FeatureView $feature, string $status, bool $defaultOn, string $audience): array
    {
        $changes = $this->commandBus->dispatch(new ChangeFeatureSettings($feature->code, $status, $defaultOn, $audience));
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
     * @param list<ConsoleClinicRow> $clinics
     */
    private function switchMany(FeatureView $feature, bool $enabled, array $clinics, string $action): int
    {
        if ([] === $clinics) {
            return 0;
        }

        $admin = $this->currentAdmin->get();
        foreach ($clinics as $clinic) {
            $this->commandBus->dispatch(new SetClinicFeature($clinic->id, $feature->code, $enabled, $admin->id, $admin->name));
        }

        $count = \count($clinics);
        $this->auditTrail->record(
            $action,
            AuditCategory::FEATURES,
            \sprintf('Fonctionnalité « %s » %s pour %d clinique%s', $feature->name, $enabled ? 'activée' : 'désactivée', $count, $count > 1 ? 's' : ''),
            details: ['Code' => $feature->code, 'Cliniques' => implode(', ', array_map(static fn (ConsoleClinicRow $c): string => $c->name, $clinics))],
        );

        return $count;
    }

    /**
     * @param array{clinic: ConsoleClinicRow, lifecycle: ClinicLifecycle, state: ClinicFeatureView} $row
     */
    private static function matches(array $row, string $search, string $state, string $plan): bool
    {
        $search = mb_strtolower(trim($search));
        $found  = '' === $search || str_contains(mb_strtolower($row['clinic']->name), $search) || str_contains($row['clinic']->slug, $search);

        return $found
            && ('all' === $state || ('on' === $state) === $row['state']->enabled)
            && ('all' === $plan || $plan === $row['clinic']->plan);
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
