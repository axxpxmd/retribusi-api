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

    public static function checkExistedJenisPendapatan($opd_id, $jenis_pendapatan_id)
    {
        $jenis_pendapatans = OPDJenisPendapatan::getJenisPendapatanByOpd($opd_id);

        if (in_array($jenis_pendapatan_id, $jenis_pendapatans->pluck('id')->toArray())) {
            return true;
        } else {
            return false;
        }
    }
}
