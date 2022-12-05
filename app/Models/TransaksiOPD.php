<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of welcome
 *
 * @author Asip Hamdi
 * Github : axxpxmd
 */

namespace App\Models;

use Carbon\Carbon;

use Illuminate\Database\Eloquent\Model;

class TransaksiOPD extends Model
{
    protected $table = 'tmtransaksi_opd';
    protected $guarded = [];

    public function opd()
    {
        return $this->belongsTo(OPD::class, 'id_opd')->select('id', 'kode', 'n_opd', 'initial', 'alamat');
    }

    public function jenis_pendapatan()
    {
        return $this->belongsTo(JenisPendapatan::class, 'id_jenis_pendapatan');
    }

    public function rincian_jenis()
    {
        return $this->belongsTo(RincianJenisPendapatan::class, 'id_rincian_jenis_pendapatan')->select('id', 'kd_jenis', 'rincian_pendapatan', 'nmr_rekening', 'nmr_rekening_denda');
    }

    public function kecamatan()
    {
        return $this->belongsTo(Kecamatan::class, 'kecamatan_id');
    }

    public function kelurahan()
    {
        return $this->belongsTo(Kelurahan::class, 'kelurahan_id');
    }

    public function userApi()
    {
        return $this->belongsTo(UserDetail::class, 'user_api');
    }

    //* ---------------------- QUERY ---------------------- *//

    // query SKRD
    public static function querySKRD($length, $opd_id, $no_skrd, $status_ttd, $start, $end)
    {
        $now  = Carbon::now();
        $date = $now->format('Y-m-d');

        $select = [
            'tmtransaksi_opd.id as id', 'no_skrd', 'no_bayar', 'nm_wajib_pajak as nama_pemohon', 'tgl_skrd_awal as tgl_skrd', 'tgl_skrd_akhir as tgl_jatuh_tempo', 'jumlah_bayar', 'status_ttd', 'jenis_pendapatan'
        ];

        $data = TransaksiOPD::select($select)
            ->join('tmjenis_pendapatan', 'tmjenis_pendapatan.id', '=', 'tmtransaksi_opd.id_jenis_pendapatan')
            ->where('status_bayar', 0)
            ->where('tgl_skrd_akhir', '>=', $date)
            ->orderBy('id', 'DESC');

        if ($opd_id != 0) {
            $data->where('id_opd', $opd_id);
        }

        if ($no_skrd != null) {
            $data->where('no_skrd', $no_skrd);
        }

        if ($status_ttd != null) {
            $data->where('status_ttd', $status_ttd);
        }

        if ($start != null ||  $end != null) {
            if ($start != null && $end == null) {
                $data->whereDate('tgl_skrd_awal', $start);
            } else {
                $data->whereBetween('tgl_skrd_awal', [$start, $end]);
            }
        }

        if ($length != null) {
            return $data->simplePaginate($length);
        } else {
            return $data->get();
        }
    }
}
