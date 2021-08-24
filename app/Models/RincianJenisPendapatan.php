<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RincianJenisPendapatan extends Model
{
    protected $table = 'tmrincian_jenis_pendapatans';
    protected $guarded = [];

    public function jenis_pendapatan()
    {
        return $this->belongsTo(JenisPendapatan::class, 'id_jenis_pendapatan');
    }
}
