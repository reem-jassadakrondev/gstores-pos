<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transport extends Model
{
    protected $table = 'transport';

    protected $primaryKey = 'tran_id';

    protected $fillable = [
        'tran_name','tran_status','tran_contact_name','tran_tel',
    ];
}
