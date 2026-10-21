<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\InheritsTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A cashier session on one terminal: opening float → sales → close with counted cash.
 * Closing assigns the terminal's next Z number and freezes `summary` (the Z report).
 */
class Shift extends Model
{
    use BelongsToOrganization, HasUlids, InheritsTenant;

    protected static bool $inheritsStore = true;

    protected $fillable = ['id', 'terminal_id', 'branch_id', 'opened_by', 'opened_at', 'opening_cash', 'note'];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_cash' => 'decimal:2',
            'counted_cash' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'summary' => 'array',
        ];
    }

    protected static function tenantParent(): array
    {
        return [Terminal::class, 'terminal_id'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $shift) {
            $shift->branch_id = Terminal::withoutGlobalScopes()->findOrFail($shift->terminal_id)->branch_id;
        });
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class)->withTrashed();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }
}
