<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Infrastructure\Adapter\FeatureCatalog;

use App\Context\Scheduling\Application\Port\ClinicFeaturesInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\System\FeatureCatalog\Application\Query\IsFeatureEnabled\IsFeatureEnabled;

final readonly class QueryBusClinicFeatures implements ClinicFeaturesInterface
{
    public function __construct(
        private QueryBusInterface $queryBus,
    ) {
    }

    public function onlineBookingOn(string $clinicId): bool
    {
        return $this->on($clinicId, 'portal.online_booking');
    }

    public function smsRemindersOn(string $clinicId): bool
    {
        return $this->on($clinicId, 'notification.sms_reminders');
    }

    private function on(string $clinicId, string $code): bool
    {
        return true === $this->queryBus->ask(new IsFeatureEnabled($clinicId, $code));
    }
}
