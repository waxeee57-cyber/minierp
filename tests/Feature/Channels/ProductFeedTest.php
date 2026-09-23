<?php

namespace Tests\Feature\Channels;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Inventory\Models\Product;
use SimpleXMLElement;
use Tests\TestCase;

class ProductFeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['erp.channels.shop_url' => 'https://bolt.example']);
        Product::factory()->create(['sku' => 'IT-1', 'name' => 'Dokkoló', 'brand' => 'Ugreen', 'category' => 'Kiegészítők', 'unit_price' => 10_000, 'stock' => 3]);
        Product::factory()->create(['sku' => 'IT-2', 'name' => 'Elfogyott egér', 'brand' => 'Logitech', 'unit_price' => 1_000, 'stock' => 0]);
        Product::factory()->create(['sku' => 'IT-3', 'brand' => 'X', 'is_active' => false]);
        Product::factory()->create(['sku' => 'IT-4', 'brand' => null]);
    }

    public function test_google_merchant_feed(): void
    {
        $res = $this->get('/feeds/google-merchant.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        $xml = new SimpleXMLElement($res->getContent());
        $items = $xml->channel->item;
        $this->assertCount(2, $items, 'inaktív és márka nélküli termék kimarad');

        $g = $items[0]->children('http://base.google.com/ns/1.0');
        $this->assertSame('IT-1', (string) $g->id);
        $this->assertSame('12700 HUF', (string) $g->price, 'bruttó ár, 27% ÁFÁ-val');
        $this->assertSame('in_stock', (string) $g->availability);
        $this->assertSame('https://bolt.example/termek/it-1', (string) $g->link);
        $this->assertSame('out_of_stock', (string) $items[1]->children('http://base.google.com/ns/1.0')->availability);
    }

    public function test_arukereso_feed(): void
    {
        $xml = new SimpleXMLElement($this->get('/feeds/arukereso.xml')->assertOk()->getContent());

        $this->assertSame('products', $xml->getName());
        $p = $xml->product[0];
        foreach (['identifier', 'manufacturer', 'name', 'category', 'producturl', 'productnumber', 'price', 'netprice'] as $field) {
            $this->assertNotSame('', (string) $p->{$field}, "kötelező mező: {$field}");
        }
        $this->assertSame('12700', (string) $p->price, 'ezres elválasztó nélkül');
        $this->assertSame('10000', (string) $p->netprice);
    }

    public function test_feeds_are_not_swallowed_by_the_spa_route(): void
    {
        $this->get('/feeds/google-merchant.xml')->assertDontSee('<div id="app">', false);
    }
}
