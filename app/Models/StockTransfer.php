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
use InvalidArgumentException;

/** Moves stock between two branches of the same store (e.g. warehouse → shop). */
class StockTransfer extends Model
{
    use Auditable, BelongsToOrganization, HasUlids, InheritsTenant;

    protected static bool $inheritsStore = true;

    protected $fillable = ['from_branch_id', 'to_branch_id', 'note', 'user_id'];

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'sent_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    protected static function tenantParent(): array
    {
        return [Branch::class, 'from_branch_id'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $transfer) {
            $to = Branch::withoutGlobalScopes()->findOrFail($transfer->to_branch_id);
            if ($to->store_id !== $transfer->store_id || $to->getKey() === $transfer->from_branch_id) {
                throw new InvalidArgumentException('Transfers go to another branch of the same store.');
            }
        });

        static::creating(function (self $transfer) {
            $transfer->status ??= DocumentStatus::Draft;
            $transfer->number ??= app(DocumentNumber::class)->next(Store::withoutGlobalScopes()->findOrFail($transfer->store_id), 'TR');
        });
    }

    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }
}
