<?php

namespace App\Http\Middleware;

use Closure;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;

class InitializeTenancyByDomainOptional extends InitializeTenancyByDomain
{
    public function handle($request, Closure $next)
    {
        try {
            return parent::handle($request, $next);
        } catch (\Exception $e) {
            return $next($request);
        }
    }
}
