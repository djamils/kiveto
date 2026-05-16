<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\ValueObject;

enum AdministrationRoute: string
{
    case AURICULAR        = 'AURICULAR';
    case CUTANEOUS        = 'CUTANEOUS';
    case IN_OVO           = 'IN_OVO';
    case INTRA_ARTICULAR  = 'INTRA_ARTICULAR';
    case INTRACARDIAC     = 'INTRACARDIAC';
    case INTRADERMAL      = 'INTRADERMAL';
    case INTRAMAMMARY     = 'INTRAMAMMARY';
    case INTRAMUSCULAR    = 'INTRAMUSCULAR';
    case INTRANASAL       = 'INTRANASAL';
    case INTRAPERITONEAL  = 'INTRAPERITONEAL';
    case INTRARUMINAL     = 'INTRARUMINAL';
    case INTRAUTERINE     = 'INTRAUTERINE';
    case INTRAVENOUS      = 'INTRAVENOUS';
    case ORAL             = 'ORAL';
    case OPHTHALMIC       = 'OPHTHALMIC';
    case OROMUCOSAL       = 'OROMUCOSAL';
    case RECTAL           = 'RECTAL';
    case SUBCUTANEOUS     = 'SUBCUTANEOUS';
    case TOPICAL          = 'TOPICAL';
    case TRANSDERMAL      = 'TRANSDERMAL';
    case INHALATION       = 'INHALATION';
    case INTRAOSSEOUS     = 'INTRAOSSEOUS';
    case INTRATRACHEAL    = 'INTRATRACHEAL';
    case INTRAVAGINAL     = 'INTRAVAGINAL';
    case INTRAVESICAL     = 'INTRAVESICAL';
    case INTRACORONARY    = 'INTRACORONARY';
    case INTRAPERICARDIAL = 'INTRAPERICARDIAL';
    case INTRAPLURAL      = 'INTRAPLURAL';
    case INTRASTERNAL     = 'INTRASTERNAL';
    case INTRACYSTIC      = 'INTRACYSTIC';
    case INTRACOLONIC     = 'INTRACOLONIC';
    case INTRAGASTRIC     = 'INTRAGASTRIC';
    case INTROINTESTINAL  = 'INTROINTESTINAL';
    case INTRALYMPHATIC   = 'INTRALYMPHATIC';
    case INTRATUMORAL     = 'INTRATUMORAL';
    case INTRAOCULAR      = 'INTRAOCULAR';
    case INTRABURSAL      = 'INTRABURSAL';
    case EPIDURAL         = 'EPIDURAL';
    case PERINEURAL       = 'PERINEURAL';
    case PERIODONTAL      = 'PERIODONTAL';
    case INTRASINUSAL     = 'INTRASINUSAL';
    case ENDOCERVICAL     = 'ENDOCERVICAL';
    case ENDOSINUSAL      = 'ENDOSINUSAL';
    case ENDOTRACHEAL     = 'ENDOTRACHEAL';
    case ENDOCARDIAL      = 'ENDOCARDIAL';
    case INTRAABDOMINAL   = 'INTRAABDOMINAL';
    case INTRAHEPATIC     = 'INTRAHEPATIC';
    case INTRARENAL       = 'INTRARENAL';
    case OTHER            = 'OTHER';
}
