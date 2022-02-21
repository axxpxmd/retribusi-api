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

use Log;
use Illuminate\Http\Request;

use App\Http\Controllers\Controller;

// Queque
use App\Jobs\CallbackJob;

// Models
use App\Models\TransaksiOPD;

class CallBackController extends Controller
{
    /**
     * Payment with BJB Virtual Account
     */
    public function callBack(Request $request)
    {
        //* Get params
        $status    = $request->status;
        $va_number = $request->va_number;
        $client_refnum    = $request->client_refnum;
        $transaction_time = $request->transaction_time;
        $transaction_amount = $request->transaction_amount;

        $ip    = $request->ip();
        $ipBJB = config('app.ipbjb');
        $ipBJB2 = config('app.ipbjb2');
        $ipKMNF = config('app.ipkmnf');

        //* NTB (encrypt no_bayar)   
        $ntb = \md5($client_refnum);

        $dataLog = [
            'status' => $status,
            'va_number' => $va_number,
            'client_refnum' => $client_refnum,
            'transaction_time' => $transaction_time,
            'transaction_amount' => $transaction_amount
        ];

        Log::info($dataLog);

        //TODO: Check Status (status must 2)
        if ($status != 2)
            return response()->json([
                'status'  => 422,
                'message' => 'Error, Status harus dibayar penuh.',
            ], 422);

        try {
            //TODO: Check IP
            // if ($ip == $ipBJB || $ip == $ipBJB2 || $ip == $ipKMNF) {
            $where = [
                'nomor_va_bjb' => $va_number,
                'no_bayar' => $client_refnum
            ];
            $data = TransaksiOPD::where($where)->first();

            if ($data == null) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Error, Data nomor bayar tidak ditemukan.',
                ], 404);
            } else {
                $data->update([
                    'ntb'        => $ntb,
                    'tgl_bayar'  => $transaction_time,
                    'updated_by' => 'BJB From API Callback',
                    'status_bayar'    => 1,
                    'chanel_bayar'    => 'BJB Virtual Account',
                    'total_bayar_bjb' => $transaction_amount
                ]);
            }

            if ($data->userApi != null) {
                $url = $data->userApi->url_callback;
                $reqBody = [
                    'nomor_va_bjb' => $va_number,
                    'no_bayar'     => $client_refnum,
                    'waktu_bayar'  => $transaction_time,
                    'jumlah_bayar' => $transaction_amount,
                    'status_bayar' => 1,
                    'channel_bayar' => 'BJB Virtual Account'
                ];
                dispatch(new CallbackJob($reqBody, $url));
            }

            return response()->json([
                'status'  => 200,
                'message' => 'Success',
            ], 200);
            // } else {
            //     return response()->json([
            //         'status'  => 401,
            //         'message' => 'Error, Akses ditolak: ' . $ip,
            //     ], 401);
            // }
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
            ], 500);
        }
    }
}
