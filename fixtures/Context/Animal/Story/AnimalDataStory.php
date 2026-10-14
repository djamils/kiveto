<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Animal\Story;

use App\Context\Animal\Domain\ValueObject\PreventiveCareKind;
use App\Fixtures\Context\Animal\Factory\AnimalEntityFactory;
use App\Fixtures\Context\Animal\Factory\OwnershipEntityFactory;
use App\Fixtures\Context\Animal\Factory\PreventiveCareRecordEntityFactory;
use App\Fixtures\Context\Animal\Factory\WeightRecordEntityFactory;
use App\Fixtures\Context\Client\Story\ClientDataStory;
use App\Fixtures\Context\Clinic\Story\ClinicDataStory;
use Zenstruck\Foundry\Story;

final class AnimalDataStory extends Story
{
    public const ANIMAL_REX_ID   = '01936e1a-1111-7000-8000-000000000001';
    public const ANIMAL_MINOU_ID = '01936e1a-2222-7000-8000-000000000002';
    public const ANIMAL_BELLA_ID = '01936e1a-3333-7000-8000-000000000003';
    public const ANIMAL_MAX_ID   = '01936e1a-4444-7000-8000-000000000004';
    public const ANIMAL_LUNA_ID  = '01936e1a-5555-7000-8000-000000000005';
    public const ANIMAL_ROCKY_ID = '01936e1a-6666-7000-8000-000000000006';

    public const ANIMAL_PEPERE_ID     = '01936e1a-cccc-7000-8000-00000000000c';
    public const ANIMAL_FILOU_ID      = '01936e1a-dddd-7000-8000-00000000000d';
    public const ANIMAL_CARAMEL_ID    = '01936e1a-eeee-7000-8000-00000000000e';
    public const ANIMAL_NOISETTE_ID   = '01936e1a-ffff-7000-8000-00000000000f';
    public const ANIMAL_NALA_ID       = '01936e1a-1010-7000-8000-000000000010';
    public const ANIMAL_GRIBOUILLE_ID = '01936e1a-1111-7000-8000-000000000011';
    public const ANIMAL_SUSHI_ID      = '01936e1a-1212-7000-8000-000000000012';
    public const ANIMAL_JAVA_ID       = '01936e1a-1313-7000-8000-000000000013';
    public const ANIMAL_CANNELLE_ID   = '01936e1a-1414-7000-8000-000000000014';

    public const ANIMAL_FELIX_ID   = '01936e1a-7777-7000-8000-000000000007';
    public const ANIMAL_BUDDY_ID   = '01936e1a-1515-7000-8000-000000000015';
    public const ANIMAL_COCO_ID    = '01936e1a-8888-7000-8000-000000000008';
    public const ANIMAL_MOKA_ID    = '01936e1a-9999-7000-8000-000000000009';
    public const ANIMAL_CHARLIE_ID = '01936e1a-aaaa-7000-8000-00000000000a';

    public function build(): void
    {
        $parisClinicId = ClinicDataStory::INDEPENDENT_CLINIC_ID;
        $lyonClinicId  = ClinicDataStory::GROUP_CLINIC_ID;

        // ===========================
        // ANIMAUX PARIS (6 animaux)
        // ===========================

        // 1. Rex - Labrador mâle de Sophie (PRIMARY), Claire (SECONDARY)
        $animal1 = AnimalEntityFactory::new()
            ->withId(self::ANIMAL_REX_ID)
            ->withClinicId($parisClinicId)
            ->withName('Rex')
            ->dog()
            ->male()
            ->neutered()
            ->withMicrochip('250269801234567')
            ->withColor('Sable')
            ->active()
            ->alive()
            ->create()
        ;

        OwnershipEntityFactory::new()
            ->withAnimalId($animal1->getId()->toString())
            ->withClientId(ClientDataStory::CLIENT_SOPHIE_ID)
            ->primary()
            ->active()
            ->create()
        ;

        OwnershipEntityFactory::new()
            ->withAnimalId($animal1->getId()->toString())
            ->withClientId(ClientDataStory::CLIENT_CLAIRE_ID)
            ->secondary()
            ->active()
            ->create()
        ;

        // 2. Minou - Chat européen femelle de Marc (PRIMARY uniquement)
        $animal2 = AnimalEntityFactory::new()
            ->withId(self::ANIMAL_MINOU_ID)
            ->withClinicId($parisClinicId)
            ->withName('Minou')
            ->cat()
            ->female()
            ->neutered()
            ->withMicrochip('250269801234568')
            ->withColor('Tigré')
            ->active()
            ->alive()
            ->create()
        ;

        OwnershipEntityFactory::new()
            ->withAnimalId($animal2->getId()->toString())
            ->withClientId(ClientDataStory::CLIENT_MARC_ID)
            ->primary()
            ->active()
            ->create()
        ;

        // 3. Bella - Chien décédé de Pierre (ARCHIVED)
        $animal3 = AnimalEntityFactory::new()
            ->withId(self::ANIMAL_BELLA_ID)
            ->withClinicId($parisClinicId)
            ->withName('Bella')
            ->dog()
            ->female()
            ->intact()
            ->withColor('Noir')
            ->archived()
            ->deceased()
            ->create()
        ;

        OwnershipEntityFactory::new()
            ->withAnimalId($animal3->getId()->toString())
            ->withClientId(ClientDataStory::CLIENT_PIERRE_ID)
            ->primary()
            ->active()
            ->create()
        ;

        // 4. Max - Golden Retriever mâle de Sophie (PRIMARY) et Marc (SECONDARY)
        $animal4 = AnimalEntityFactory::new()
            ->withId(self::ANIMAL_MAX_ID)
            ->withClinicId($parisClinicId)
            ->withName('Max')
            ->withBreed('Golden Retriever', \App\Context\Animal\Domain\ValueObject\Species::DOG)
            ->male()
            ->neutered()
            ->withMicrochip('250269801234569')
            ->withColor('Doré')
            ->active()
            ->alive()
            ->create()
        ;

        OwnershipEntityFactory::new()
            ->withAnimalId($animal4->getId()->toString())
            ->withClientId(ClientDataStory::CLIENT_SOPHIE_ID)
            ->primary()
            ->active()
            ->create()
        ;

        OwnershipEntityFactory::new()
            ->withAnimalId($animal4->getId()->toString())
            ->withClientId(ClientDataStory::CLIENT_MARC_ID)
            ->secondary()
            ->active()
            ->create()
        ;

        // 5. Luna - Chat Persan femelle de Claire (PRIMARY uniquement)
        $animal5 = AnimalEntityFactory::new()
            ->withId(self::ANIMAL_LUNA_ID)
            ->withClinicId($parisClinicId)
            ->withName('Luna')
            ->withBreed('Persan', \App\Context\Animal\Domain\ValueObject\Species::CAT)
            ->female()
            ->intact()
            ->withMicrochip('250269801234570')
            ->withColor('Blanc')
            ->active()
            ->alive()
            ->create()
        ;

        OwnershipEntityFactory::new()
            ->withAnimalId($animal5->getId()->toString())
            ->withClientId(ClientDataStory::CLIENT_CLAIRE_ID)
            ->primary()
            ->active()
            ->create()
        ;

        // 6. Rocky - Beagle mâle de Sophie (PRIMARY uniquement)
        $animal6 = AnimalEntityFactory::new()
            ->withId(self::ANIMAL_ROCKY_ID)
            ->withClinicId($parisClinicId)
            ->withName('Rocky')
            ->withBreed('Beagle', \App\Context\Animal\Domain\ValueObject\Species::DOG)
            ->male()
            ->intact()
            ->withMicrochip('250269801234571')
            ->withColor('Tricolore')
            ->active()
            ->alive()
            ->create()
        ;

        OwnershipEntityFactory::new()
            ->withAnimalId($animal6->getId()->toString())
            ->withClientId(ClientDataStory::CLIENT_SOPHIE_ID)
            ->primary()
            ->active()
            ->create()
        ;

        // ===========================
        // ANIMAUX LYON (9 animaux)
        // ===========================

        // 7. Felix - Chat Siamois mâle d'Émilie (PRIMARY)
        $animal7 = AnimalEntityFactory::new()
            ->withId(self::ANIMAL_FELIX_ID)
            ->withClinicId($lyonClinicId)
            ->withName('Felix')
            ->withBreed('Siamois', \App\Context\Animal\Domain\ValueObject\Species::CAT)
            ->male()
            ->neutered()
            ->withMicrochip('250269801234572')
            ->withColor('Seal Point')
            ->active()
            ->alive()
            ->create()
        ;

        OwnershipEntityFactory::new()
            ->withAnimalId($animal7->getId()->toString())
            ->withClientId(ClientDataStory::CLIENT_EMILIE_ID)
            ->primary()
            ->active()
            ->create()
        ;

        // 8. Buddy - Labrador mâle de Thomas (ARCHIVED avec animal)
        $animal8 = AnimalEntityFactory::new()
            ->withId(self::ANIMAL_BUDDY_ID)
            ->withClinicId($lyonClinicId)
            ->withName('Buddy')
            ->dog()
            ->male()
            ->intact()
            ->withColor('Chocolat')
            ->archived()
            ->alive()
            ->create()
        ;

        OwnershipEntityFactory::new()
            ->withAnimalId($animal8->getId()->toString())
            ->withClientId(ClientDataStory::CLIENT_THOMAS_ID)
            ->primary()
            ->active()
            ->create()
        ;

        // 9. Coco - Caniche femelle de Nathalie (PRIMARY) et Émilie (SECONDARY)
        $animal9 = AnimalEntityFactory::new()
            ->withId(self::ANIMAL_COCO_ID)
            ->withClinicId($lyonClinicId)
            ->withName('Coco')
            ->withBreed('Caniche', \App\Context\Animal\Domain\ValueObject\Species::DOG)
            ->female()
            ->neutered()
            ->withMicrochip('250269801234573')
            ->withColor('Blanc')
            ->active()
            ->alive()
            ->create()
        ;

        OwnershipEntityFactory::new()
            ->withAnimalId($animal9->getId()->toString())
            ->withClientId(ClientDataStory::CLIENT_NATHALIE_ID)
            ->primary()
            ->active()
            ->create()
        ;

        OwnershipEntityFactory::new()
            ->withAnimalId($animal9->getId()->toString())
            ->withClientId(ClientDataStory::CLIENT_EMILIE_ID)
            ->secondary()
            ->active()
            ->create()
        ;

        // 10. Moka - Chat Maine Coon femelle de Nathalie (PRIMARY)
        $animal10 = AnimalEntityFactory::new()
            ->withId(self::ANIMAL_MOKA_ID)
            ->withClinicId($lyonClinicId)
            ->withName('Moka')
            ->withBreed('Maine Coon', \App\Context\Animal\Domain\ValueObject\Species::CAT)
            ->female()
            ->intact()
            ->withMicrochip('250269801234574')
            ->withColor('Brun Tabby')
            ->active()
            ->alive()
            ->create()
        ;

        OwnershipEntityFactory::new()
            ->withAnimalId($animal10->getId()->toString())
            ->withClientId(ClientDataStory::CLIENT_NATHALIE_ID)
            ->primary()
            ->active()
            ->create()
        ;

        // 11. Charlie - Berger Allemand mâle d'Émilie (PRIMARY uniquement)
        $animal11 = AnimalEntityFactory::new()
            ->withId(self::ANIMAL_CHARLIE_ID)
            ->withClinicId($lyonClinicId)
            ->withName('Charlie')
            ->withBreed('Berger Allemand', \App\Context\Animal\Domain\ValueObject\Species::DOG)
            ->male()
            ->neutered()
            ->withMicrochip('250269801234575')
            ->withColor('Noir et feu')
            ->active()
            ->alive()
            ->create()
        ;

        OwnershipEntityFactory::new()
            ->withAnimalId($animal11->getId()->toString())
            ->withClientId(ClientDataStory::CLIENT_EMILIE_ID)
            ->primary()
            ->active()
            ->create()
        ;

        // 12. Nala - Chat Chartreux femelle de Laurent (PRIMARY uniquement)
        $animal12 = AnimalEntityFactory::new()
            ->withId(self::ANIMAL_NALA_ID)
            ->withClinicId($lyonClinicId)
            ->withName('Nala')
            ->withBreed('Chartreux', \App\Context\Animal\Domain\ValueObject\Species::CAT)
            ->female()
            ->neutered()
            ->withMicrochip('250269801234576')
            ->withColor('Gris bleu')
            ->active()
            ->alive()
            ->create()
        ;

        OwnershipEntityFactory::new()
            ->withAnimalId($animal12->getId()->toString())
            ->withClientId(ClientDataStory::CLIENT_LAURENT_ID)
            ->primary()
            ->active()
            ->create()
        ;

        // 13. Gribouille - Lapin nain mâle d'Émilie (PRIMARY uniquement)
        $animal13 = AnimalEntityFactory::new()
            ->withId(self::ANIMAL_GRIBOUILLE_ID)
            ->withClinicId($lyonClinicId)
            ->withName('Gribouille')
            ->withBreed('Lapin nain', \App\Context\Animal\Domain\ValueObject\Species::NAC)
            ->male()
            ->intact()
            ->withColor('Blanc et gris')
            ->active()
            ->alive()
            ->create()
        ;

        OwnershipEntityFactory::new()
            ->withAnimalId($animal13->getId()->toString())
            ->withClientId(ClientDataStory::CLIENT_EMILIE_ID)
            ->primary()
            ->active()
            ->create()
        ;

        // 14. Sushi - Chat Bengal mâle de Nathalie (PRIMARY uniquement)
        $animal14 = AnimalEntityFactory::new()
            ->withId(self::ANIMAL_SUSHI_ID)
            ->withClinicId($lyonClinicId)
            ->withName('Sushi')
            ->withBreed('Bengal', \App\Context\Animal\Domain\ValueObject\Species::CAT)
            ->male()
            ->intact()
            ->withMicrochip('250269801234577')
            ->withColor('Rosetté brun')
            ->active()
            ->alive()
            ->create()
        ;

        OwnershipEntityFactory::new()
            ->withAnimalId($animal14->getId()->toString())
            ->withClientId(ClientDataStory::CLIENT_NATHALIE_ID)
            ->primary()
            ->active()
            ->create()
        ;

        // 15. Java - Border Collie femelle d'Émilie (PRIMARY uniquement)
        $animal15 = AnimalEntityFactory::new()
            ->withId(self::ANIMAL_JAVA_ID)
            ->withClinicId($lyonClinicId)
            ->withName('Java')
            ->withBreed('Border Collie', \App\Context\Animal\Domain\ValueObject\Species::DOG)
            ->female()
            ->neutered()
            ->withMicrochip('250269801234578')
            ->withColor('Noir et blanc')
            ->active()
            ->alive()
            ->create()
        ;

        OwnershipEntityFactory::new()
            ->withAnimalId($animal15->getId()->toString())
            ->withClientId(ClientDataStory::CLIENT_EMILIE_ID)
            ->primary()
            ->active()
            ->create()
        ;

        // ==========================================
        // UI EDGE-CASE FIXTURES — PARIS (7 animaux)
        // ==========================================
        // Aurélie Chevalier owns 7 active animals.
        // This ensures the "+N" overflow badge is always visible in the clients list
        // (threshold: more than 4 active primary-owned animals).

        // Seven active animals for Aurélie — mix of dogs and cats covering the "+N" badge threshold.
        // The first three carry fixed ids: the admission board uses them to fill
        // its surgery, hospitalisation and checkout columns.
        $aurelieAnimals = [
            ['Pépère', 'dog', null, 'Beige', self::ANIMAL_PEPERE_ID],
            ['Filou', 'cat', null, 'Roux', self::ANIMAL_FILOU_ID],
            ['Caramel', 'dog', 'Teckel', 'Marron', self::ANIMAL_CARAMEL_ID],
            ['Noisette', 'cat', 'Européen', 'Tigré', self::ANIMAL_NOISETTE_ID],
            ['Cannelle', 'dog', null, 'Fauve', self::ANIMAL_CANNELLE_ID],
            ['Doudou', 'cat', 'Angora', 'Blanc', null],
            ['Bisou', 'cat', 'Ragdoll', 'Bicolore', null],
        ];

        foreach ($aurelieAnimals as [$animalName, $species, $breed, $color, $animalId]) {
            $factory = AnimalEntityFactory::new()
                ->withClinicId($parisClinicId)
                ->withName($animalName)
                ->withColor($color)
                ->male()
                ->neutered()
                ->active()
                ->alive()
            ;

            if (null !== $animalId) {
                $factory = $factory->withId($animalId);
            }

            $factory = null !== $breed
                ? $factory->withBreed($breed, \App\Context\Animal\Domain\ValueObject\Species::from($species))
                : ('dog' === $species ? $factory->dog() : $factory->cat());

            $aurelie = $factory->create();

            OwnershipEntityFactory::new()
                ->withAnimalId($aurelie->getId()->toString())
                ->withClientId(ClientDataStory::CLIENT_AURELIE_ID)
                ->primary()
                ->active()
                ->create()
            ;
        }

        $this->buildWeighingsAndPreventiveCare();
    }

    /**
     * Weighings and preventive acts, dated relative to today so that the
     * "late", "due soon" and "up to date" states all stay on screen whenever
     * the fixtures are loaded. Rex carries the full picture (a curve, one act
     * of each state); Minou a single weighing; every other animal keeps the
     * empty states.
     */
    private function buildWeighingsAndPreventiveCare(): void
    {
        $clinicId = ClinicDataStory::INDEPENDENT_CLINIC_ID;
        $today    = new \DateTimeImmutable('today 10:00');

        $rexCurve = [
            '-11 months' => [27_400, null],
            '-9 months'  => [27_600, 5],
            '-7 months'  => [27_900, null],
            '-5 months'  => [28_300, 5],
            '-3 months'  => [28_800, 6],
            '-5 weeks'   => [29_100, 6],
        ];

        foreach ($rexCurve as $when => [$grams, $score]) {
            WeightRecordEntityFactory::new()
                ->forAnimal($clinicId, self::ANIMAL_REX_ID)
                ->weighing($grams, $today->modify($when), $score)
                ->create()
            ;
        }

        WeightRecordEntityFactory::new()
            ->forAnimal($clinicId, self::ANIMAL_MINOU_ID)
            ->weighing(4_200, $today->modify('-2 months'), 5)
            ->create()
        ;

        $rexActs = [
            // Up to date.
            [PreventiveCareKind::VACCINATION, 'CHPPiL', '-7 months', '+5 months', 'VX-77219'],
            [PreventiveCareKind::VACCINATION, 'Rage', '-14 months', '+22 months', 'RB-40118'],
            // Late.
            [PreventiveCareKind::INTERNAL_PARASITE, 'Vermifugation', '-4 months', '-1 month', null],
            // Due soon.
            [PreventiveCareKind::EXTERNAL_PARASITE, 'Puces et tiques', '-3 weeks', '+10 days', null],
            // An older act of the same protocol: the sheet only keeps the latest.
            [PreventiveCareKind::VACCINATION, 'CHPPiL', '-19 months', '-7 months', 'VX-61020'],
        ];

        foreach ($rexActs as [$kind, $label, $administered, $nextDue, $batch]) {
            PreventiveCareRecordEntityFactory::new()
                ->forAnimal($clinicId, self::ANIMAL_REX_ID)
                ->act($kind, $label, $today->modify($administered), $today->modify($nextDue), $batch)
                ->create()
            ;
        }

        // An act without a next due date: listed, never chased.
        PreventiveCareRecordEntityFactory::new()
            ->forAnimal($clinicId, self::ANIMAL_MINOU_ID)
            ->act(PreventiveCareKind::VACCINATION, 'Typhus-Coryza', $today->modify('-2 months'))
            ->create()
        ;
    }
}
