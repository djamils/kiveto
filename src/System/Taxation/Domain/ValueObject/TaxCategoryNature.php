<?php

declare(strict_types=1);

namespace App\System\Taxation\Domain\ValueObject;

/**
 * Whether a tax category covers the supply of goods or of services.
 *
 * The distinction decides how an invoice is framed for the tax authority
 * (EN16931 BT-23 « cadre de facturation »: goods, services or mixed) and
 * when its VAT falls due; it belongs to the category, like its rate.
 */
enum TaxCategoryNature: string
{
    case GOODS    = 'goods';
    case SERVICES = 'services';
}
