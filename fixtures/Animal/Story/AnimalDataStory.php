<?php

declare(strict_types=1);

namespace App\Fixtures\Animal\Story;

use App\Fixtures\Animal\Factory\AnimalEntityFactory;
use App\Fixtures\Animal\Factory\OwnershipEntityFactory;
use App\Fixtures\Client\Story\ClientDataStory;
use App\Fixtures\Clinic\Story\ClinicDataStory;
use Zenstruck\Foundry\Story;

final class AnimalDataStory extends Story
{
    public const ANIMAL_REX_ID   = '01936e1a-1111-7000-8000-000000000001';
    public const ANIMAL_MINOU_ID = '01936e1a-2222-7000-8000-000000000002';
    public const ANIMAL_BELLA_ID = '01936e1a-3333-7000-8000-000000000003';

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
            ->withClinicId($parisClinicId)
            ->withName('Max')
            ->withBreed('Golden Retriever', \App\Animal\Domain\ValueObject\Species::DOG)
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
            ->withClinicId($parisClinicId)
            ->withName('Luna')
            ->withBreed('Persan', \App\Animal\Domain\ValueObject\Species::CAT)
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
            ->withClinicId($parisClinicId)
            ->withName('Rocky')
            ->withBreed('Beagle', \App\Animal\Domain\ValueObject\Species::DOG)
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
        // ANIMAUX LYON (5 animaux)
        // ===========================

        // 7. Felix - Chat Siamois mâle d'Émilie (PRIMARY)
        $animal7 = AnimalEntityFactory::new()
            ->withClinicId($lyonClinicId)
            ->withName('Felix')
            ->withBreed('Siamois', \App\Animal\Domain\ValueObject\Species::CAT)
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
            ->withClinicId($lyonClinicId)
            ->withName('Coco')
            ->withBreed('Caniche', \App\Animal\Domain\ValueObject\Species::DOG)
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
            ->withClinicId($lyonClinicId)
            ->withName('Moka')
            ->withBreed('Maine Coon', \App\Animal\Domain\ValueObject\Species::CAT)
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
            ->withClinicId($lyonClinicId)
            ->withName('Charlie')
            ->withBreed('Berger Allemand', \App\Animal\Domain\ValueObject\Species::DOG)
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
    }
}
