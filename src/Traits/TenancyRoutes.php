<?php

namespace Ramesh\Cms\Traits;

trait TenancyRoutes
{
    protected function webMiddleware(array $middleware = ['web']): array
    {
        if (config('cms.tenancy_enabled')) {
            return array_merge($middleware, [
                \App\Http\Middleware\InitializeTenancyByDomainOptional::class,
            ]);
        }
        return $middleware;
    }

    protected function adminMiddleware(): array
    {
        return $this->webMiddleware(['web', 'Admin']);
    }
}
