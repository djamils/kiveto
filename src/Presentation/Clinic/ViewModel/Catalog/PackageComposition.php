<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Catalog;

use App\Context\Catalog\Application\Command\Package\AddPackageComponent\AddPackageComponent;
use App\Context\Catalog\Application\Command\Package\RemovePackageComponent\RemovePackageComponent;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * A package's sheet is saved whole, its composition included: the page sends
 * the lines it shows, in order, and only the lines that changed are removed and
 * added again — the domain knows no « change the quantity ».
 */
final readonly class PackageComposition
{
    public function __construct(private CommandBusInterface $commandBus)
    {
    }

    /**
     * The lines the page sent, or null when it sent none.
     *
     * @return list<array{type: string, item: string, q: int}>|null
     */
    public static function wanted(Request $request): ?array
    {
        if (!$request->request->has('components')) {
            return null;
        }

        $decoded = json_decode($request->request->getString('components'), true);
        $lines   = [];

        foreach (\is_array($decoded) ? $decoded : [] as $line) {
            if (!\is_array($line) || !\is_string($line['type'] ?? null) || !\is_string($line['item'] ?? null)) {
                continue;
            }

            $quantity = $line['q'] ?? 1;

            $lines[] = [
                'type' => $line['type'],
                'item' => $line['item'],
                'q'    => \is_int($quantity) ? $quantity : 1,
            ];
        }

        return $lines;
    }

    /**
     * @param list<array{id: string, itemType: string, itemId: string, quantity: int, weight: int}> $current
     * @param list<array{type: string, item: string, q: int}>                                       $wanted
     */
    public function sync(string $packageId, string $clinicId, array $current, array $wanted): void
    {
        $key  = static fn (string $type, string $item, int $q, int $weight): string => $type . '|' . $item . '|' . $q . '|' . $weight;
        $want = [];

        foreach ($wanted as $weight => $line) {
            $want[$key($line['type'], $line['item'], $line['q'], $weight)] = $line + ['weight' => $weight];
        }

        foreach ($current as $line) {
            $k = $key($line['itemType'], $line['itemId'], $line['quantity'], $line['weight']);

            if (isset($want[$k])) {
                unset($want[$k]);
                continue;
            }

            $this->commandBus->dispatch(new RemovePackageComponent($packageId, $line['id'], $clinicId));
        }

        foreach ($want as $line) {
            $this->commandBus->dispatch(new AddPackageComponent(
                packageId: $packageId,
                clinicId: $clinicId,
                itemType: $line['type'],
                itemId: $line['item'],
                quantity: $line['q'],
                weight: $line['weight'],
            ));
        }
    }
}
