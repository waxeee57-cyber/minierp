<?php

namespace Modules\Channels\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Channels\Services\ProductFeed;

class FeedController extends Controller
{
    public function google(ProductFeed $feed): Response
    {
        return $this->xml($feed->googleMerchant());
    }

    public function arukereso(ProductFeed $feed): Response
    {
        return $this->xml($feed->arukereso());
    }

    private function xml(string $body): Response
    {
        // A feedolvasók óránként-naponta húzzák: 15 perc gyorsítótár bőven elég, a készlet így friss marad.
        return response($body, 200, ['Content-Type' => 'application/xml; charset=UTF-8', 'Cache-Control' => 'public, max-age=900']);
    }
}
