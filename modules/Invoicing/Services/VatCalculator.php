<?php

namespace Modules\Invoicing\Services;

/**
 * ÁFA-számítás tételenként kerekítve (a NAV tételszintű adatokat vár),
 * az összesítő a tételek összege, így a számla mindig belsőleg egyezik.
 */
class VatCalculator
{
    public function __construct(private readonly float $rate) {}

    /**
     * @param  iterable<array{sku:string, name:string, quantity:int, unit_price:int}>  $items
     * @return array{lines: list<array>, net:int, vat:int, gross:int, rate:float}
     */
    public function calculate(iterable $items): array
    {
        $lines = [];
        $net = $vat = 0;

        foreach ($items as $i => $item) {
            $lineNet = $item['unit_price'] * $item['quantity'];
            $lineVat = (int) round($lineNet * $this->rate, 0, PHP_ROUND_HALF_UP);

            $lines[] = $item + [
                'line_number' => $i + 1,
                'net' => $lineNet,
                'vat' => $lineVat,
                'gross' => $lineNet + $lineVat,
            ];

            $net += $lineNet;
            $vat += $lineVat;
        }

        return ['lines' => $lines, 'net' => $net, 'vat' => $vat, 'gross' => $net + $vat, 'rate' => $this->rate];
    }
}
