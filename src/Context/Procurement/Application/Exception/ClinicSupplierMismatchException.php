<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Exception;

final class ClinicSupplierMismatchException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('The purchase order clinic or supplier does not match the unmatched delivery.');
    }
}
