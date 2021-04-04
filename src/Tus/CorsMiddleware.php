<?php

namespace Bjerke\Bread\Tus;

use TusPhp\Middleware\TusMiddleware;
use TusPhp\Request;
use TusPhp\Response;

class CorsMiddleware implements TusMiddleware
{
    public function handle(Request $request, Response $response): void
    {
        $response->setHeaders([
            'Access-Control-Allow-Credentials' => 'true'
        ]);
    }
}
