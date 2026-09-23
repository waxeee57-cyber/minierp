<?php

namespace Modules\Channels\Services;

use Illuminate\Support\Collection;
use Modules\Inventory\Contracts\ProductCatalog;
use XMLWriter;

/**
 * Termékfeedek árösszehasonlítóknak és hirdetési platformoknak.
 * Forrás: a ProductCatalog szerződés, így a feed mindig a valós készletet és árat mutatja.
 *
 * - Google Merchant Center: RSS 2.0 + g: névtér, bruttó ár „12345 HUF” alakban.
 * - Árukereső: <products><product>, bruttó és nettó ár ezres elválasztó nélkül.
 */
class ProductFeed
{
    private const GOOGLE_NS = 'http://base.google.com/ns/1.0';

    public function __construct(private readonly ProductCatalog $catalog) {}

    public function googleMerchant(): string
    {
        $x = $this->writer();
        $x->startElement('rss');
        $x->writeAttribute('version', '2.0');
        $x->writeAttribute('xmlns:g', self::GOOGLE_NS);
        $x->startElement('channel');
        $x->writeElement('title', config('erp.invoicing.seller.name'));
        $x->writeElement('link', $this->shopUrl());
        $x->writeElement('description', 'Termékfeed – Mini-ERP');

        foreach ($this->products() as $p) {
            $x->startElement('item');
            $x->writeElement('g:id', $p['sku']);
            $x->writeElement('g:title', $p['name']);
            $x->writeElement('g:description', $p['description'] ?: $p['name']);
            $x->writeElement('g:link', $this->url('product', $p['sku']));
            $x->writeElement('g:image_link', $this->url('image', $p['sku']));
            $x->writeElement('g:availability', $p['stock'] > 0 ? 'in_stock' : 'out_of_stock');
            $x->writeElement('g:price', "{$p['gross']} HUF");
            $x->writeElement('g:condition', 'new');
            $x->writeElement('g:brand', $p['brand']);
            $x->writeElement('g:mpn', $p['sku']);
            if ($p['category']) {
                $x->writeElement('g:product_type', $p['category']);
            }
            $x->endElement();
        }

        $x->endElement();
        $x->endElement();

        return $x->outputMemory();
    }

    public function arukereso(): string
    {
        $x = $this->writer();
        $x->startElement('products');

        foreach ($this->products() as $p) {
            $x->startElement('product');
            $x->writeElement('identifier', $p['sku']);
            $x->writeElement('manufacturer', $p['brand']);
            $x->writeElement('name', $p['name']);
            $x->writeElement('category', $p['category'] ?? 'Egyéb');
            $x->writeElement('producturl', $this->url('product', $p['sku']));
            $x->writeElement('productnumber', $p['sku']);
            $x->writeElement('price', (string) $p['gross']);
            $x->writeElement('netprice', (string) $p['unit_price']);
            $x->endElement();
        }

        $x->endElement();

        return $x->outputMemory();
    }

    /**
     * Csak a feedre alkalmas termékek: márka nélkül a Google és az Árukereső is elutasítja.
     *
     * @return Collection<int, array>
     */
    public function products(): Collection
    {
        $rate = (float) config('erp.invoicing.vat_rate');

        return $this->catalog->activeProducts()
            ->filter(fn ($p) => filled($p['brand']))
            ->map(fn ($p) => $p + ['gross' => (int) round($p['unit_price'] * (1 + $rate))])
            ->values();
    }

    private function writer(): XMLWriter
    {
        $x = new XMLWriter;
        $x->openMemory();
        $x->setIndent(true);
        $x->setIndentString('  ');
        $x->startDocument('1.0', 'UTF-8');

        return $x;
    }

    private function shopUrl(): string
    {
        return rtrim((string) config('erp.channels.shop_url'), '/');
    }

    private function url(string $kind, string $sku): string
    {
        $pattern = config("erp.channels.{$kind}_url", $kind === 'product' ? '{shop}/termek/{sku}' : '{shop}/kepek/{sku}.jpg');

        return strtr($pattern, ['{shop}' => $this->shopUrl(), '{sku}' => rawurlencode(strtolower($sku))]);
    }
}
