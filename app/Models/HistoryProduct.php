<?php
//© 2020 Copyright: Tahu Coding
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HistoryProduct extends Model
{
        //demi keamanan kalian harusnya ubah ini ke fillable ya
        protected $guarded = [];

        public function user(){
                return $this->belongsTo(User::class);
        }
}
