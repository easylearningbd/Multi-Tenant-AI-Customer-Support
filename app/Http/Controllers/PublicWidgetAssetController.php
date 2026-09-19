<?php

namespace App\Http\Controllers;

use App\Services\PublicWidgetContext;
use App\Services\WidgetLoaderScript;
use Illuminate\Http\Response;

final class PublicWidgetAssetController extends Controller
{
    public function __invoke(string $publicWidget, PublicWidgetContext $context, WidgetLoaderScript $scripts): Response
    {
        $widget = $context->resolve($publicWidget);

        return response($scripts->render($widget), 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300, stale-while-revalidate=600',
            'Cross-Origin-Resource-Policy' => 'cross-origin',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
