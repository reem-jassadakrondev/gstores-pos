<?php

namespace App\Models\Concerns;

use App\Support\Audit\AuditLogger;

/** Writes an audit_logs row for every create/update/delete. Hidden attributes are never logged. */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn ($model) => $model->writeAudit('created', null, $model->auditable($model->getAttributes())));

        static::updated(function ($model) {
            $changed = array_keys($model->getChanges());
            $changed = array_diff($changed, ['updated_at', 'last_seen_at'], $model->getHidden());
            if (! $changed) {
                return;
            }
            $model->writeAudit(
                'updated',
                $model->auditable(array_intersect_key($model->getOriginal(), array_flip($changed))),
                $model->auditable(array_intersect_key($model->getAttributes(), array_flip($changed))),
            );
        });

        static::deleted(fn ($model) => $model->writeAudit('deleted', $model->auditable($model->getOriginal()), null));
    }

    protected function auditable(array $attributes): array
    {
        $hidden = array_merge($this->getHidden(), ['created_at', 'updated_at']);

        return array_diff_key($attributes, array_flip($hidden));
    }

    protected function writeAudit(string $event, ?array $before, ?array $after): void
    {
        app(AuditLogger::class)->record(class_basename($this).'.'.$event, $this, $before, $after);
    }
}
