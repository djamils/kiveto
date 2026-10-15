<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Query\FindByMicrochip;

use App\Context\Animal\Application\Port\AnimalReadRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class FindByMicrochipHandler
{
    public function __construct(
        private AnimalReadRepositoryInterface $readRepository,
    ) {
    }

    public function __invoke(FindByMicrochip $query): ?AnimalMicrochipView
    {
        // Readers and typists add spaces every three digits: « 250 269 812 340 011 ».
        $chip = preg_replace('/\D/', '', $query->microchipNumber) ?? '';

        if ('' === $chip) {
            return null;
        }

        return $this->readRepository->findByMicrochip($chip, $query->clinicId);
    }
}
