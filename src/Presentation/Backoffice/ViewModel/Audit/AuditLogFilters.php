<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\ViewModel\Audit;

use App\System\Audit\Application\Query\ExportAuditLog\ExportAuditLog;
use App\System\Audit\Application\Query\SearchAuditLog\SearchAuditLog;
use App\System\Audit\Domain\ValueObject\ActorKind;
use App\System\Audit\Domain\ValueObject\AuditCategory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;

/**
 * The filters of the audit log page, read from its address. Unknown values
 * are ignored rather than refused: a hand-edited link still shows the log.
 */
final readonly class AuditLogFilters
{
    public const array CATEGORIES = [
        'access'   => 'Accès',
        'clinics'  => 'Cliniques',
        'users'    => 'Utilisateurs',
        'support'  => 'Support',
        'features' => 'Fonctionnalités',
        'registry' => 'ANMV',
    ];

    public const array ACTORS = [
        'kiveto'  => 'Équipe Kiveto',
        'staff'   => 'Utilisateurs clinique',
        'system'  => 'Système',
        'unknown' => 'Inconnu',
    ];

    /** Days of each period; 0 = since the beginning. */
    public const array PERIODS = [
        '1'    => 'Dernières 24 h',
        '7'    => '7 derniers jours',
        '30'   => '30 derniers jours',
        '90'   => '90 derniers jours',
        'tout' => 'Depuis le début',
    ];

    private const string DEFAULT_PERIOD = '7';

    public function __construct(
        public ?AuditCategory $category = null,
        public ?string $clinicId = null,
        public ?ActorKind $actorKind = null,
        public ?string $userId = null,
        public string $period = self::DEFAULT_PERIOD,
        public string $text = '',
        public int $page = 1,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $query  = $request->query;
        $period = $query->getString('period', self::DEFAULT_PERIOD);

        return new self(
            category: AuditCategory::tryFrom($query->getString('category')),
            clinicId: self::uuid($query->getString('clinic')),
            actorKind: ActorKind::tryFrom($query->getString('actor')),
            userId: self::uuid($query->getString('user')),
            period: \array_key_exists($period, self::PERIODS) ? $period : self::DEFAULT_PERIOD,
            text: trim($query->getString('q')),
            page: max(1, $query->getInt('page', 1)),
        );
    }

    public function search(int $perPage): SearchAuditLog
    {
        return new SearchAuditLog(
            page: $this->page,
            perPage: $perPage,
            category: $this->category,
            clinicId: $this->clinicId,
            actorKind: $this->actorKind,
            userId: $this->userId,
            periodDays: $this->periodDays(),
            text: '' !== $this->text ? $this->text : null,
        );
    }

    public function export(): ExportAuditLog
    {
        return new ExportAuditLog(
            category: $this->category,
            clinicId: $this->clinicId,
            actorKind: $this->actorKind,
            userId: $this->userId,
            periodDays: $this->periodDays(),
            text: '' !== $this->text ? $this->text : null,
        );
    }

    /**
     * The address parameters of these filters, page excluded, with some
     * replaced (null removes one).
     *
     * @param array<string, string|null> $overrides
     *
     * @return array<string, string>
     */
    public function params(array $overrides = []): array
    {
        $params = array_merge([
            'category' => $this->category?->value,
            'clinic'   => $this->clinicId,
            'actor'    => $this->actorKind?->value,
            'user'     => $this->userId,
            'period'   => self::DEFAULT_PERIOD !== $this->period ? $this->period : null,
            'q'        => '' !== $this->text ? $this->text : null,
        ], $overrides);

        return array_filter($params, static fn (?string $value): bool => null !== $value && '' !== $value);
    }

    private function periodDays(): ?int
    {
        return 'tout' === $this->period ? null : (int) $this->period;
    }

    private static function uuid(string $value): ?string
    {
        return Uuid::isValid($value) ? $value : null;
    }
}
