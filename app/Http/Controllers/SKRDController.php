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

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Crypt;

// Models
use App\Models\TransaksiOPD;

class SKRDController extends Controller
{
    public function checkNoBayar()
    {
        return response()->json([
            'status'  => 404,
            'message' => 'No bayar tidak boleh kosong.'
        ], 404);
    }

    public function invoice(Request $request, $no_bayar)
    {
        $no_bayar = $no_bayar;

        try {
            $data = TransaksiOPD::where('no_bayar', $no_bayar)->first();

            if ($data == null) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Tidak ada data disini.'
                ], 404);
            }

            if ($data->rincian_jenis == null) {
                $kode_rekening = '-';
                $nama_rekening = '-';
            } else {
                $kode_rekening = $data->rincian_jenis->nmr_rekening;
                $nama_rekening = $data->rincian_jenis->rincian_pendapatan;
            }

            $data = array(
                'id' => Crypt::encrypt($data->id),
                'no_bayar'      => $data->no_bayar,
                'nama_penyetor' => $data->nm_wajib_pajak,
                'alamat' => $data->alamat_wp,
                'lokasi' => $data->lokasi,

                'nama_skpd'        => $data->opd->n_opd,
                'kode_skpd'        => $data->opd->kode,
                'kode_rekening'    => $kode_rekening,
                'nama_rekening'    => $nama_rekening,
                'jenis_pendapaan'  => $data->jenis_pendapatan->jenis_pendapatan,
                'uraian_retribusi' => $data->uraian_retribusi,

                'ketetapan' => $data->jumlah_bayar,
                // 'denda'        => $data->denda,
                'diskon'       => $data->diskon,
                'total_bayar'  => $data->total_bayar,
                // 'total_bayar_bjb'  => $data->total_bayar_bjb,

                'no_skrd'        => $data->no_skrd,
                'tgl_skrd_awal'  => $data->tgl_skrd_awal,
                'tgl_skrd_akhir' => $data->tgl_skrd_akhir,
                // 'tgl_bayar' => $data->tgl_bayar,
                // 'no_bku'    => $data->no_bku,
                // 'tgl_bku'   => $data->tgl_bku,

                // 'id_transaksi'  => $data->id_transaksi,
                'status_denda'  => $data->status_denda,
                // 'status_diskon' => $data->status_diskon,
                'status_bayar'  => $data->status_bayar
            );

            return response()->json([
                'status'  => 200,
                'message' => 'Succesfully',
                'data'    => $data
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $id = Crypt::decrypt($request->id);

        $status_bayar = $request->status_bayar;
        $tgl_bayar    = $request->tgl_bayar;
        $no_bku       = $request->no_bku;
        $tgl_bku      = $request->tgl_bku;
        $denda        = $request->denda;
        $ntb          = $request->ntb;
        $total_bayar_bjb = $request->total_bayar_bjb;
        $chanel_bayar    = $request->chanel_bayar;

        try {
            $data = TransaksiOPD::where('id', $id)->first();

            if ($data == null) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Error, Tidak ada data disini.'
                ], 404);
            }

            if ($denda != null) {
                $data->update([
                    'denda' => $denda,
                ]);
            }

            $data->update([
                'status_bayar' => $status_bayar,
                'tgl_bayar'    => $tgl_bayar,
                'no_bku'       => $no_bku,
                'tgl_bku' => $tgl_bku,
                'denda'   => $denda,
                'ntb'     => $ntb,
                'total_bayar_bjb' => $total_bayar_bjb,
                'chanel_bayar'    => $chanel_bayar
            ]);

            return response()->json([
                'status'  => 200,
                'message' => 'Success, Data berhasil diperbaharui.',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
            ], 500);
        }
    }
}
