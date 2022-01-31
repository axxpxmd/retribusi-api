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

namespace App\Http\Controllers\BJB;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

use App\Http\Controllers\Controller;

// Models
use App\Models\TransaksiOPD;

class InvoiceController extends Controller
{
    public function invoice($no_bayar)
    {
        //* Get params
        $no_bayar = $no_bayar;

        try {
            $data = TransaksiOPD::where('no_bayar', $no_bayar)->first();

            if ($data == null)
                return response()->json([
                    'status'  => 404,
                    'message' => 'Data nomor bayar tidak ditemukan.'
                ], 404);


            //* Check rincian jenis (kode_rekening, nama_rekening)
            if ($data->rincian_jenis == null) {
                $kode_rekening = '-';
                $nama_rekening = '-';
            } else {
                $kode_rekening = $data->rincian_jenis->nmr_rekening;
                $nama_rekening = $data->rincian_jenis->rincian_pendapatan;
            }

            $data = array(
                'id'     => Crypt::encrypt($data->id),
                'alamat' => $data->alamat_wp,
                'lokasi' => $data->lokasi,
                'no_bayar'      => $data->no_bayar,
                'nama_penyetor' => $data->nm_wajib_pajak,

                'nama_skpd'        => $data->opd->n_opd,
                'kode_skpd'        => $data->opd->kode,
                'kode_rekening'    => $kode_rekening,
                'nama_rekening'    => $nama_rekening,
                'jenis_pendapaan'  => $data->jenis_pendapatan->jenis_pendapatan,
                'uraian_retribusi' => $data->uraian_retribusi,

                'ketetapan'   => $data->jumlah_bayar,
                'diskon'      => $data->diskon == null ? 0 : $data->diskon,
                'total_bayar' => $data->total_bayar,

                'no_skrd'        => $data->no_skrd,
                'tgl_skrd_awal'  => $data->tgl_skrd_awal,
                'tgl_skrd_akhir' => $data->tgl_skrd_akhir,

                'status_denda'  => $data->status_denda,
                'status_bayar'  => $data->status_bayar
            );

            return response()->json([
                'status'  => 200,
                'message' => 'Success',
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

        //* Get params
        $ntb    = $request->ntb;
        $denda  = $request->denda;
        $no_bku = $request->no_bku;
        $tgl_bku   = $request->tgl_bku;
        $tgl_bayar = $request->tgl_bayar;
        $status_bayar = $request->status_bayar;
        $chanel_bayar = $request->chanel_bayar;
        $total_bayar_bjb = $request->total_bayar_bjb;


        try {
            $data = TransaksiOPD::where('id', $id)->first();

            if ($data == null) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Data nomor bayar tidak ditemukan.'
                ], 404);
            }

            $data->update([
                'ntb'    => $ntb,
                'denda'  => $denda,
                'no_bku' => $no_bku,
                'tgl_bku'   => $tgl_bku,
                'tgl_bayar' => $tgl_bayar,
                'updated_by'   => 'Bank BJB',
                'status_bayar' => $status_bayar,
                'chanel_bayar' => $chanel_bayar,
                'total_bayar_bjb' => $total_bayar_bjb,
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
