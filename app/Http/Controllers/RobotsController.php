<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * /robots.txt served by a route, not a static file, so the Sitemap line follows app.url
 * and the private areas are listed from configuration (docs/15).
 */
class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        $lines = ['User-agent: *'];

        foreach ((array) config('avytra.seo.robots_disallow') as $path) {
            $lines[] = 'Disallow: '.$path;
        }

        // app.url on purpose: crawlers must get the canonical host, whatever host served this request.
        $lines[] = '';
        $lines[] = 'Sitemap: '.rtrim((string) config('app.url'), '/').'/sitemap.xml';

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
