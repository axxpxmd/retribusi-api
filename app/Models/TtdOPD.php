<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;



class TtdOPD extends Model
{
    protected $table   = 'tr_ttd_opds';
    protected $guarded = [];

    public function opd()
    {
        return $this->belongsTo(OPD::class, 'id_opd');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function userDetail()
    {
        return $this->belongsTo(UserDetail::class, 'user_id', 'user_id');
    }
}
