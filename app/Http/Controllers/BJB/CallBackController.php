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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

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

        //TODO: LOG
        LOG::channel('va')->info('status:' . $status . ' | ' . 'va number:' . $va_number . ' | ' . 'client refnum:' . $client_refnum . ' | ' . 'transaction time:' . $transaction_time . ' | ' . 'transaction amount:' . $transaction_amount);

        $ip    = $request->ip();
        $ipBJB = config('app.ipbjb');
        $ipBJB2 = config('app.ipbjb2');
        $ipKMNF = config('app.ipkmnf');

        //* NTB (encrypt no_bayar)   
        $ntb = \md5($client_refnum);

        //TODO: Check Status (status must 2)
        if ($status != 2)
            return response()->json([
                'status'  => 422,
                'message' => 'Error, Status harus dibayar penuh.',
            ], 422);

        try {
            //TODO: Check IP
            if ($ip == $ipBJB || $ip != $ipBJB2 || $ip != $ipKMNF) {
                $where = [
                    'nomor_va_bjb' => $va_number,
                    'no_bayar' => $client_refnum
                ];
                $data = TransaksiOPD::where($where)->first();

                //* Check Data
                if ($data == null)
                    return response()->json([
                        'status'  => 404,
                        'message' => 'Error, Data nomor bayar tidak ditemukan.',
                    ], 404);

                //* Check Amount
                if ($data->jumlah_bayar != $transaction_amount) {
                    return response()->json([
                        'status'  => 403,
                        'message' => 'Error, nominal bayar tidak sesuai.',
                    ], 403);
                }

                $data->update([
                    'ntb' => $ntb,
                    'tgl_bayar'  => $transaction_time,
                    'updated_by' => 'BJB From API Callback',
                    'status_bayar' => 1,
                    'chanel_bayar' => 'BJB Virtual Account',
                    'total_bayar_bjb' => $transaction_amount
                ]);

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
                    'response_code'  => 0000,
                    'response_message' => 'Success',
                ]);
            } else {
                return response()->json([
                    'status'  => 401,
                    'message' => 'Error, Akses ditolak: ' . $ip,
                ], 401);
            }
        } catch (\Throwable $th) {
            //TODO: LOG ERROR
            LOG::channel('va')->error($th->getMessage());

            return response()->json([
                'status' => 500,
                'message' => 'server error',
            ], 500);
        }
    }

    /**
     * Payment with BJB QRIS
     */
    public function callbackQRIS(Request $request)
    {
        //TODO: LOG INFO
        LOG::channel('qris')->info('invoiceID:' . $request->InvoiceNumber . ' | ' . 'type:' . $request->type . ' | ' . 'transaction date:' . $request->transcationDate . ' | ' . 'transaction amount:' . $request->transcationAmount . ' | ' . 'customer name:' . $request->customerName);

        $this->validate($request, [
            'transcationDate' => 'required',
            'transcationAmount' => 'required',
            'customerName' => 'required',
            'InvoiceNumber' => 'required'
        ]);

        try {
            $time = Carbon::now();

            //* Get Params
            $type = $request->type;
            $transcationDate = $request->transcationDate;
            $transcationAmount = (int) str_replace(['.', 'Rp', ' ', ','], '', $request->transcationAmount);
            $customerName = $request->customerName;
            $InvoiceNumber = $request->InvoiceNumber;

            //TODO: Check type
            if ($type != 'TRANSACTION')
                return response()->json([
                    'status'  => 422,
                    'message' => 'type harus berisi TRANSACTION.',
                ], 422);

            $data = TransaksiOPD::where('invoice_id', $InvoiceNumber)->first();

            if ($data == null)
                return response()->json([
                    'status'  => 404,
                    'message' => 'Error, Nomor invoice tidak ditemukan.',
                ], 404);

            //* NTB (encrypt no_bayar)   
            $ntb = \md5($data->no_bayar);

            $data->update([
                'ntb' => $ntb,
                'tgl_bayar'  => Carbon::createFromFormat('d/m/Y H:i:s', $transcationDate)->format('Y-m-d H:i:s'),
                'updated_by' => 'BJB From API Callback',
                'status_bayar' => 1,
                'chanel_bayar' => 'QRIS | ' . $customerName,
                'total_bayar_bjb' => $transcationAmount
            ]);

            $status = [
                'code' => 200,
                'description' => 'OK',
                'datetime' => $time->format('Y-m-d') . 'T' . $time->format('H:i:s')
            ];

            //TODO: LOG INFO
            LOG::channel('qris')->info('invoiceID:' . $InvoiceNumber . ' | ', $status);

            return response()->json([
                'metadata'  => null,
                'status' => $status,
            ]);
        } catch (\Throwable $th) {
            //TODO: LOG ERROR
            LOG::channel('qris')->error($th->getMessage());

            return response()->json([
                'status' => 500,
                'message' => 'server error',
            ], 500);
        }
    }
}
