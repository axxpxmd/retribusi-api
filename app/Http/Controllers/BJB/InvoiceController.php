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

use App\Http\Services\VABJBRes;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

// Queque
use App\Jobs\CallbackJob;
use App\Jobs\WhatsAppJob;

// Models
use App\Models\TransaksiOPD;
use Illuminate\Support\Carbon;

class InvoiceController extends Controller
{
    public function __construct(VABJBRes $vabjbres)
    {
        $this->vabjbres = $vabjbres;
    }

    public function invoice($no_bayar)
    {
        //* Get params
        $no_bayar = $no_bayar;

        try {
            $data = TransaksiOPD::where('no_bayar', $no_bayar)->first();

            if (!$data)
                return response()->json([
                    'status'  => 404,
                    'message' => 'Data nomor bayar tidak ditemukan.'
                ], 404);

            $data = array(
                'id'     => \base64_encode($data->id),
                'alamat' => $data->alamat_wp,
                'lokasi' => $data->lokasi,
                'no_bayar'         => $data->no_bayar,
                'nama_penyetor'    => $data->nm_wajib_pajak,
                'nama_skpd'        => $data->opd->n_opd,
                'kode_skpd'        => $data->opd->kode,
                'kode_rekening'    => $data->rincian_jenis ? $data->rincian_jenis->nmr_rekening : '-',
                'nama_rekening'    => $data->rincian_jenis ? $data->rincian_jenis->rincian_pendapatan : '-',
                'jenis_pendapaan'  => $data->jenis_pendapatan->jenis_pendapatan,
                'uraian_retribusi' => $data->uraian_retribusi,
                'ketetapan'        => $data->jumlah_bayar,
                'diskon'           => $data->diskon == null ? 0 : $data->diskon,
                'total_bayar'      => $data->total_bayar,
                'no_skrd'          => $data->no_skrd,
                'tgl_skrd_awal'    => $data->tgl_skrd_awal,
                'tgl_skrd_akhir'   => $data->tgl_skrd_akhir,
                'status_denda'     => $data->status_denda,
                'status_bayar'     => $data->status_bayar
            );

            //TODO: LOG
            LOG::channel('atm')->info('Menampilkan SKRD ', $data);

            return response()->json([
                'status'  => 200,
                'message' => 'Success',
                'data'    => $data
            ], 200);
        } catch (\Throwable $th) {
            //TODO: LOG ERROR
            LOG::channel('atm')->error($th->getMessage(), ['no_bayar' => $no_bayar]);

            return response()->json([
                'status' => 500,
                'message' => 'server error',
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        //* Get params
        $id     = \base64_decode($id);
        $ntb    = $request->ntb;
        $denda  = $request->denda;
        $no_bku = $request->no_bku;
        $tgl_bku         = $request->tgl_bku;
        $tgl_bayar       = $request->tgl_bayar;
        $chanel_bayar    = $request->chanel_bayar;
        $status_bayar    = $request->status_bayar;
        $total_bayar_bjb = $request->total_bayar_bjb;

        //TODO: LOG
        LOG::channel('atm')->info('Update Data | ' . 'ntb:' . $ntb . ' | ' . 'denda:' . $denda . ' | ' . 'no_bku:' . $no_bku . ' | ' . 'tgl_bku:' . $tgl_bku . ' | ' . 'tgl_bayar:' . $tgl_bayar . ' | ' . 'status_bayar:' . $status_bayar . ' | ' . 'total_bayar_bjb:' . $total_bayar_bjb . ' | ' . 'chanel_bayar:' . $chanel_bayar);

        /* Tahapan :
         *  1. tmtransaksi_opd
         *  2. Make VA Expired
         *  3. Forward callback from BJB to Client
         *  4. Send invoice from Whatsapp
         */

        try {
            //* Tahap 1
            $data = TransaksiOPD::where('id', $id)->first();

            if (!$data)
                return response()->json([
                    'status'  => 404,
                    'message' => 'Data nomor bayar tidak ditemukan.'
                ], 404);

            $data->update([
                'ntb'    => $ntb,
                'denda'  => $denda,
                'no_bku' => $no_bku,
                'tgl_bku'   => $tgl_bku,
                'tgl_bayar' => $tgl_bayar,
                'updated_by'      => 'Bank BJB',
                'status_bayar'    => $status_bayar,
                'chanel_bayar'    => $chanel_bayar ? $chanel_bayar : 'ATM BJB',
                'total_bayar_bjb' => $total_bayar_bjb,
            ]);

            //* Tahap 2
            $amount       = \strval((int) str_replace(['.', 'Rp', ' '], '', $data->jumlah_bayar));
            $customerName = $data->nm_wajib_pajak;
            $va_number    = (int) $data->nomor_va_bjb;
            $clientRefnum = $data->no_bayar;
            $expiredDate  = Carbon::now()->addMinutes(20)->format('Y-m-d H:i:s');

            if ($va_number) {
                //TODO: Get Token VA
                list($err, $errMsg, $tokenBJB) = $this->vabjbres->getTokenBJBres(1);

                //TODO: Update VA BJB (make Va expired)
                list($err, $errMsg, $VABJB) = $this->vabjbres->updateVABJBres($tokenBJB, $amount, $expiredDate, $customerName, $va_number, 1, $clientRefnum);
            }

            //* Tahap 3
            if ($data->userApi != null) {
                $url = $data->userApi->url_callback;
                $reqBody = [
                    'nomor_va_bjb' => $data->nomor_va_bjb,
                    'no_bayar'     => $data->no_bayar,
                    'waktu_bayar'  => $tgl_bayar,
                    'jumlah_bayar' => $total_bayar_bjb,
                    'status_bayar' => 1,
                    'chanel_bayar' => $chanel_bayar ? $chanel_bayar : 'ATM BJB',
                ];
                dispatch(new CallbackJob($reqBody, $url));
            }

            //* Tahap 4
            if ($data->no_telp) {
                $params = [
                    'ntb'  => $ntb,
                    'data' => $data,
                    'tgl_bayar'       => $tgl_bayar,
                    'chanel_bayar'    => $chanel_bayar,
                    'total_bayar_bjb' => $total_bayar_bjb,
                ];
                dispatch(new WhatsAppJob($params));
            }

            return response()->json([
                'status'  => 200,
                'message' => 'Success, Data berhasil diperbaharui.',
            ], 200);
        } catch (\Throwable $th) {
            //TODO: LOG ERROR
            LOG::channel('atm')->error($th->getMessage(), ['no_bayar' => $data->no_bayar]);

            return response()->json([
                'status' => 500,
                'message' => 'server error',
            ], 500);
        }
    }
}
