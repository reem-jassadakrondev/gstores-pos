<?php

namespace App\Support\Audit;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Store;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    public function __construct(private CurrentTenant $tenant) {}

    public function record(
        string $action,
        ?Model $subject = null,
        ?array $before = null,
        ?array $after = null,
        array $meta = [],
        ?int $userId = null,
    ): AuditLog {
        $request = app()->runningInConsole() ? null : request();
        $user = auth()->user();

        return AuditLog::create([
            'organization_id' => $subject?->getAttribute('organization_id')
                ?? ($subject instanceof Organization ? $subject->getKey() : $this->tenant->id()),
            'store_id' => $subject?->getAttribute('store_id')
                ?? ($subject instanceof Store ? $subject->getKey() : null),
            'user_id' => $userId ?? ($user instanceof User ? $user->getKey() : null),
            'terminal_id' => $this->tenant->terminal()?->getKey(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'before' => $before,
            'after' => $after,
            'meta' => array_filter([
                'ip' => $request?->ip(),
                'user_agent' => $request ? substr((string) $request->userAgent(), 0, 255) : null,
                ...$meta,
            ], fn ($v) => $v !== null),
        ]);
    }
}
