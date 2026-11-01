<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Input;

use ApiPlatform\Metadata\ApiProperty;
use App\Context\ClientPortal\Application\Command\BookPortalAppointmentWithNewPet\BookPortalAppointmentWithNewPetHandler;
use Symfony\Component\Validator\Constraints as Assert;

/** The animal a with-new-pet booking registers (sex and neutering unknown). */
final class NewPet
{
    #[Assert\NotBlank(message: 'Le nom de l\'animal est obligatoire.', normalizer: 'trim')]
    #[Assert\Length(max: BookPortalAppointmentWithNewPetHandler::NAME_MAX_LENGTH, maxMessage: 'Le nom de l\'animal comporte {{ limit }} caractères au plus.')]
    public string $name;

    #[ApiProperty(schema: ['type' => 'string', 'enum' => BookPortalAppointmentWithNewPetHandler::SPECIES])]
    #[Assert\NotBlank(message: 'L\'espèce de l\'animal est obligatoire.')]
    #[Assert\Choice(choices: BookPortalAppointmentWithNewPetHandler::SPECIES, message: 'Choisissez l\'espèce de l\'animal : chien, chat, NAC ou autre.')]
    public string $species;
}
