<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * List the public pages search engines should index.
     */
    public function __invoke(): Response
    {
        return response()
            ->view('site.sitemap', [
                'routeNames' => ['home', 'pricing', 'about', 'contact', 'privacy', 'terms', 'register'],
            ])
            ->header('Content-Type', 'application/xml');
    }
}
