<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Support\Tenancy\CurrentTenant;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Copies Filament's selected tenant into CurrentTenant so the global scopes apply. */
class SetCurrentTenant
{
    public function __construct(private CurrentTenant $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $organization = Filament::getTenant();
        if ($organization instanceof Organization) {
            $this->tenant->set($organization);
        }

        return $next($request);
    }
}
