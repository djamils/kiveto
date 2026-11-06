<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\FeatureCatalog\Domain;

use App\System\FeatureCatalog\Domain\FeatureCatalogue;
use App\System\FeatureCatalog\Domain\FeatureDefinition;
use App\System\FeatureCatalog\Domain\FeatureStatus;
use App\System\FeatureCatalog\Domain\UnknownFeatureException;
use PHPUnit\Framework\TestCase;

final class FeatureCatalogueTest extends TestCase
{
    /**
     * The README's table: code, initial status, default, legacy state.
     */
    public function testTheCatalogueListsTheFeaturesInTheConsolesOrder(): void
    {
        self::assertSame([
            ['compliance.controlled_substances', 'Registre des stupéfiants', FeatureStatus::UPCOMING, false, false],
            ['portal.online_booking', 'Prise de RDV en ligne', FeatureStatus::AVAILABLE, true, true],
            ['notification.sms_reminders', 'Rappels SMS', FeatureStatus::AVAILABLE, true, true],
            ['clinic.hospitalisation', 'Hospitalisation', FeatureStatus::UPCOMING, false, false],
            ['billing.electronic_invoicing', 'Facturation électronique', FeatureStatus::BETA, false, true],
            ['support.write_mode', 'Écriture en session support', FeatureStatus::INTERNAL, false, false],
        ], array_map(
            static fn (FeatureDefinition $definition): array => [$definition->code, $definition->name, $definition->initialStatus, $definition->initialDefaultOn, $definition->legacyOn],
            FeatureCatalogue::all(),
        ));

        foreach (FeatureCatalogue::all() as $definition) {
            self::assertNotSame('', $definition->description, $definition->code);
            self::assertNotSame('', $definition->effect, $definition->code);
        }
    }

    public function testAFeatureIsFoundByItsCode(): void
    {
        $definition = FeatureCatalogue::get(FeatureCatalogue::SMS_REMINDERS);

        self::assertSame(['notification.sms_reminders', 'Rappels SMS', 'Rappels de RDV et de vaccins par SMS.'], [$definition->code, $definition->name, $definition->description]);
        self::assertSame(FeatureCatalogue::SUPPORT_WRITE_MODE, FeatureCatalogue::get('support.write_mode')->code);
    }

    public function testAnUnknownCodeIsRefused(): void
    {
        $this->expectException(UnknownFeatureException::class);
        $this->expectExceptionMessage('Fonctionnalité inconnue : « portal.teleconsultation ».');

        FeatureCatalogue::get('portal.teleconsultation');
    }
}
