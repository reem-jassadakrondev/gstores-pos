<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Shared by every store of the organization, so purchasing can be pooled. */
class Supplier extends Model
{
    use Auditable, BelongsToOrganization, HasUlids, SoftDeletes;

    protected $fillable = ['name', 'tax_id', 'contact_name', 'phone', 'email', 'address', 'payment_terms_days', 'note', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
