<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataWP extends Model
{
    protected $table = 'tmdata_wp';
    protected $guarded = [];

    public function jenis_pendapatan()
    {
        return $this->belongsTo(JenisPendapatan::class, 'id_jenis_pendapatan');
    }

    public function rincian_jenis()
    {
        return $this->belongsTo(RincianJenisPendapatan::class, 'id_rincian_jenis_pendapatan');
    }

    public function opd()
    {
        return $this->belongsTo(OPD::class, 'id_opd');
    }

    public function kelurahan()
    {
        return $this->belongsTo(Kelurahan::class, 'kelurahan_id');
    }

    public function kecamatan()
    {
        return $this->belongsTo(Kecamatan::class, 'kecamatan_id');
    }

    public static function dataWP($opd_id, $jenis_pendapatan_id)
    {
        $data = DataWP::with(['jenis_pendapatan', 'rincian_jenis', 'opd'])->orderBy('id', 'DESC');

        if ($opd_id != 0) {
            $data->where('id_opd', $opd_id);
        }

        if ($jenis_pendapatan_id) {
            $data->where('id_jenis_pendapatan', $jenis_pendapatan_id);
        }

        return $data->get();
    }
}
