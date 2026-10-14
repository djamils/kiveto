<?php

declare(strict_types=1);

namespace App\Context\Admission\Application\Query\ListActiveAdmissions;

use App\Shared\Application\Bus\QueryInterface;

/**
 * Every patient currently on site for the clinic, wherever they are: waiting
 * room, consultation, surgery, hospitalisation or the checkout desk.
 */
final readonly class ListActiveAdmissions implements QueryInterface
{
    public function __construct(public string $clinicId)
    {
    }
}
