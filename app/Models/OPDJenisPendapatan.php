<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OPDJenisPendapatan extends Model
{
    protected $table   = 'tr_opd_jenis_pendapatans';
    protected $guarded = [];
    public $timestamps = false;

    public function opd()
    {
        return $this->belongsTo(OPD::class, 'id_opd');
    }

    public function jenis_pendapatan()
    {
        return $this->belongsTo(JenisPendapatan::class, 'id_jenis_pendapatan');
    }

    public function transaksi_pendapatan()
    {
        return $this->hasMany(TransaksiOPD::class, 'id_jenis_pendapatan', 'id_jenis_pendapatan');
    }

    // 
    public static function getJenisPendapatanByOpd($opd_id)
    {
        $datas = OPDJenisPendapatan::select('tr_opd_jenis_pendapatans.id_jenis_pendapatan as id', 'tmjenis_pendapatan.jenis_pendapatan')
            ->join('tmjenis_pendapatan', 'tmjenis_pendapatan.id', '=', 'tr_opd_jenis_pendapatans.id_jenis_pendapatan')
            ->where('tr_opd_jenis_pendapatans.id_opd', $opd_id)
            ->get();

        return $datas;
    }
}
