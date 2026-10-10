<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Command\ChangeClientAuthorizedPerson;

final readonly class ChangeClientAuthorizedPerson
{
    /**
     * @param AuthorizedPersonDto|null $authorizedPerson null withdraws the authorization
     */
    public function __construct(
        public string $clinicId,
        public string $clientId,
        public ?AuthorizedPersonDto $authorizedPerson = null,
    ) {
    }
}
