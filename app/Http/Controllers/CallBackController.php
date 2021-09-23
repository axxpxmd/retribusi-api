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

// Models
use App\Models\TransaksiOPD;

class CallBackController extends Controller
{
    public function callBack(Request $request)
    {
        $status        = $request->status;
        $va_number     = $request->va_number;
        $client_refnum = $request->client_refnum;
        $transaction_time   = $request->transaction_time;
        $transaction_amount = $request->transaction_amount;

        $ip     = $request->ip();
        $ipBJB  = config('app.ipbjb');
        $ipKMNF = config('app.ipkmnf');

        dd($ip . ' - ' . $ipKMNF);

        $ntb = \md5($client_refnum);

        // Check IP
        if ($ip != $ipBJB || $ip != $ipKMNF)
            return response()->json([
                'status'  => 401,
                'message' => 'Error, Akses ditolak.',
            ], 401);

        // Check Status (status must 2)
        if ($status != 2)
            return response()->json([
                'status'  => 422,
                'message' => 'Error, Status harus dibayar penuh.',
            ], 422);

        try {
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
                    'status_bayar' => 1,
                    'tgl_bayar'    => $transaction_time,
                    'total_bayar_bjb' => $transaction_amount,
                    'updated_by'      => 'BJB From API Callback',
                    'ntb'      => $ntb,
                    'check_ip' => $ip
                ]);
            }

            return response()->json([
                'status'  => 200,
                'message' => 'Success',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
            ], 500);
        }
    }
}
