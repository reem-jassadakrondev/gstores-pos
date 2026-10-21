<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\InheritsTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Cash put into (pay_in) or taken out of (pay_out) the drawer during a shift, outside of sales. */
class CashMovement extends Model
{
    use BelongsToOrganization, HasUlids, InheritsTenant;

    protected $fillable = ['id', 'shift_id', 'type', 'amount', 'reason', 'user_id', 'occurred_at'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function tenantParent(): array
    {
        return [Shift::class, 'shift_id'];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
