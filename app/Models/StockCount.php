<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\InheritsTenant;
use App\Support\Documents\DocumentNumber;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Stocktake of one branch (optionally one category). Completing it posts the differences. */
class StockCount extends Model
{
    use Auditable, BelongsToOrganization, HasUlids, InheritsTenant;

    protected static bool $inheritsStore = true;

    protected $fillable = ['branch_id', 'category_id', 'note', 'user_id'];

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    protected static function tenantParent(): array
    {
        return [Branch::class, 'branch_id'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $count) {
            $count->status ??= DocumentStatus::Draft;
            $count->number ??= app(DocumentNumber::class)->next(Store::withoutGlobalScopes()->findOrFail($count->store_id), 'SC');
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockCountItem::class);
    }
}
