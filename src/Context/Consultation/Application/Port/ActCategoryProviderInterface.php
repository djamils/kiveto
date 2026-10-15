<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Port;

/**
 * The category the clinic's catalogue files each act under, so a billing line
 * can be recognised as a surgery or a laboratory analysis by its code.
 */
interface ActCategoryProviderInterface
{
    public const string SURGERY    = 'SURGERY';
    public const string LABORATORY = 'LABORATORY';

    /**
     * Codes of the clinic's acts in these categories, archived ones included.
     *
     * @param list<string> $categories
     *
     * @return array<string, string> act code => category
     */
    public function actCodesByCategory(string $clinicId, array $categories): array;
}
