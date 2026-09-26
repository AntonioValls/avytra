<?php

namespace App\Http\Controllers;

use App\Support\Seo\Sitemap;
use Illuminate\Http\Response;

/**
 * /sitemap.xml (docs/15): generated and cached by App\Support\Seo\Sitemap, no package.
 */
class SitemapController extends Controller
{
    public function __invoke(Sitemap $sitemap): Response
    {
        return response($sitemap->xml(), 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
