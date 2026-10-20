<?php

namespace App\Http\Middleware;

use App\Models\Terminal;
use App\Support\Tenancy\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * For POS API routes behind auth:sanctum: the token must belong to an active terminal
 * whose branch, store and organization are usable. Sets the tenant for the request.
 */
class EnsureTerminal
{
    public function __construct(private CurrentTenant $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $terminal = $request->user();

        if (! $terminal instanceof Terminal || ! $terminal->is_active) {
            return response()->json(['message' => 'เครื่องนี้ไม่ได้รับอนุญาต', 'code' => 'terminal_inactive'], 403);
        }

        $terminal->loadMissing(['branch', 'store', 'organization']);

        if (! $terminal->branch?->is_active || ! $terminal->store?->is_active || ! $terminal->organization) {
            return response()->json(['message' => 'สาขาหรือร้านนี้ถูกปิดใช้งาน', 'code' => 'store_inactive'], 403);
        }

        if (! $terminal->organization->isOperational()) {
            return response()->json(['message' => 'บัญชีหมดอายุทดลองหรือถูกระงับ กรุณาติดต่อเจ้าของร้าน', 'code' => 'subscription_inactive'], 402);
        }

        $this->tenant->setTerminal($terminal);

        if (! $terminal->last_seen_at || $terminal->last_seen_at->lt(now()->subMinute())) {
            $terminal->forceFill(['last_seen_at' => now()])->saveQuietly();
        }

        return $next($request);
    }
}
