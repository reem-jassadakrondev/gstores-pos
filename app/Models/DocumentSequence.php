<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\InheritsTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class DocumentSequence extends Model
{
    use BelongsToOrganization, HasUlids, InheritsTenant;

    protected static bool $inheritsStore = true;

    protected $fillable = ['store_id', 'doc_type', 'period', 'next_no'];

    protected static function tenantParent(): array
    {
        return [Store::class, 'store_id'];
    }
}
