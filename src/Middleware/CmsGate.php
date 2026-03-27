<?php

namespace Ramesh\Cms\Middleware;

use Closure;
use Illuminate\Http\Request;

class CmsGate
{
    public function handle(Request $request, Closure $next, string $resource = null): mixed
    {
        if ($resource) {
            CGate::resouce($resource);
        }

        return $next($request);
    }
}
