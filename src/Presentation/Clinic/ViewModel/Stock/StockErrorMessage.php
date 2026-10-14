<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Stock;

use App\Context\Inventory\Application\Exception\ArticleNotActiveException;
use App\Context\Inventory\Application\Exception\ArticleNotFoundException;
use App\Context\Inventory\Domain\StockItem\Exception\ArchivedStockItemException;
use App\Context\Inventory\Domain\StockItem\Exception\ConcurrentStockModificationException;
use App\Context\Inventory\Domain\StockItem\Exception\DuplicateStockItemException;
use App\Context\Inventory\Domain\StockItem\Exception\InconsistentLotDataException;
use App\Context\Inventory\Domain\StockItem\Exception\InsufficientStockException;
use App\Context\Inventory\Domain\StockItem\Exception\LotAlreadyTerminatedException;
use App\Context\Inventory\Domain\StockItem\Exception\LotExpiredException;
use App\Context\Inventory\Domain\StockItem\Exception\LotNotExpiredException;
use App\Context\Inventory\Domain\StockItem\Exception\LotNotFoundException;
use App\Context\Inventory\Domain\StockItem\Exception\LotRecalledException;
use App\Context\Inventory\Domain\StockItem\Exception\NegativeQuantityException;
use App\Context\Inventory\Domain\StockItem\Exception\StockItemNotFoundException;
use App\Context\Inventory\Domain\StockItem\Exception\StockNotEmptyOnArchiveException;
use App\Context\Inventory\Domain\StockItem\Exception\UnitMismatchException;
use App\Context\Procurement\Application\Exception\DiscontinuedCatalogEntryException;
use App\Context\Procurement\Application\Exception\PurchaseOrderClosedOrCancelledException;
use App\Context\Procurement\Application\Exception\ReceiptQuantityExceedsOrderedException;
use App\Context\Procurement\Application\Exception\SupplierAccountDisabledException;
use App\Context\Procurement\Domain\PurchaseOrder\Exception\InvalidPurchaseOrderStatusTransitionException;

/**
 * Says in French what the Inventory and Procurement domains refused.
 *
 * The bounded context speaks English to its developers; the dispensary speaks
 * French to its users. Mapping the exception type rather than rewording the
 * domain keeps the invariant in one place and the sentence in the other.
 */
final class StockErrorMessage
{
    public static function of(\Throwable $exception): string
    {
        return match (true) {
            $exception instanceof InsufficientStockException => 'Le stock disponible ne couvre pas cette sortie.',

            $exception instanceof StockNotEmptyOnArchiveException => 'Il reste du stock : sortez-le avant d’archiver cet article.',

            $exception instanceof ArchivedStockItemException => 'Le suivi de cet article est archivé : rétablissez-le avant d’enregistrer un mouvement.',

            $exception instanceof LotExpiredException => 'Ce lot est périmé et ne peut plus être utilisé.',

            $exception instanceof LotRecalledException => 'Ce lot a été rappelé et ne peut plus être utilisé.',

            $exception instanceof LotAlreadyTerminatedException => 'Ce lot est déjà clos.',

            $exception instanceof LotNotExpiredException => 'Ce lot n’est pas périmé.',

            $exception instanceof InconsistentLotDataException => 'Ce numéro de lot est déjà en stock avec une autre date de péremption.',

            $exception instanceof DiscontinuedCatalogEntryException => 'Le fournisseur ne référence plus cet article.',

            $exception instanceof SupplierAccountDisabledException => 'Le compte chez ce fournisseur est désactivé.',

            $exception instanceof InvalidPurchaseOrderStatusTransitionException,
            $exception instanceof PurchaseOrderClosedOrCancelledException => 'Cette commande ne peut plus être modifiée.',

            $exception instanceof ReceiptQuantityExceedsOrderedException => 'La quantité reçue dépasse ce qui a été commandé.',

            $exception instanceof LotNotFoundException => 'Ce lot est introuvable.',

            $exception instanceof StockItemNotFoundException,
            $exception instanceof ArticleNotFoundException => 'Cet article n’est pas suivi en stock.',

            $exception instanceof ArticleNotActiveException => 'Cet article est archivé au catalogue.',

            $exception instanceof DuplicateStockItemException => 'Cet article est déjà suivi en stock.',

            $exception instanceof NegativeQuantityException => 'Une quantité ne peut pas être négative.',

            $exception instanceof UnitMismatchException => 'L’unité saisie n’est pas celle de l’article.',

            $exception instanceof ConcurrentStockModificationException => 'Ce stock vient d’être modifié ailleurs. Rechargez la page et recommencez.',

            // Anything the domain raises without a name of its own still says
            // what it refused.
            default => $exception->getMessage(),
        };
    }
}
