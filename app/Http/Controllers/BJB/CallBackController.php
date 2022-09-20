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

use App\Http\Services\VABJB;
use App\Http\Controllers\Controller;

// Queque
use App\Jobs\CallbackJob;
use App\Jobs\TangselPayCallbackJob;

// Models
use App\Models\TransaksiOPD;

class CallBackController extends Controller
{
    public function __construct(VABJB $vabjb)
    {
        $this->vabjb = $vabjb;
    }

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

                //* Cek Status Bayar
                if ($data->status_bayar == 1) {
                    $status = [
                        'status'  => 404,
                        'message' => 'Data ini sudah dibayar menggunakan ' . $data->chanel_bayar,
                    ];

                    //TODO: LOG ERROR
                    LOG::channel('va')->error('No Bayar:' . $client_refnum . ' | ', $status);

                    return response()->json($status, 404);
                }

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

                //* Sent callback to Tangselpay
                $urlTangselPay = 'http://192.168.200.160/v1/intern/callback-va';
                $reqBodyTangselPay = [
                    'status' => $status,
                    'va_number' => $va_number,
                    'client_refnum' => $client_refnum,
                    'transaction_time' => $transaction_time,
                    'transaction_amount' => $transaction_amount
                ];
                dispatch(new TangselPayCallbackJob($reqBodyTangselPay, $urlTangselPay));

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
        LOG::channel('qris')->info('invoiceID:' . $request->invoiceNumber . ' | ' . 'type:' . $request->type . ' | ' . 'transaction date:' . $request->transactionDate . ' | ' . 'transaction amount:' . $request->transactionAmount . ' | ' . 'customer name:' . $request->customerName . ' | ' . 'rrn:' . $request->rrn);

        $this->validate($request, [
            'transactionDate' => 'required',
            'transactionAmount' => 'required',
            'customerName' => 'required',
            'invoiceNumber' => 'required'
        ]);

        /* Tahapan :
         *  1. tmtransaksi_opd
         *  2. Make VA Expired
         */

        try {
            $time = Carbon::now();

            //* Get Params
            $type = $request->type;
            $transactionDate = $request->transactionDate;
            $transactionAmount = (int) str_replace(['.', 'Rp', ' ', ','], '', $request->transactionAmount);
            $customerName = $request->customerName;
            $invoiceNumber = $request->invoiceNumber;
            $rrn = $request->rrn;
            $merchantName = $request->merchantName;
            $transcationStatus = $request->transcationStatus;
            $transcationReference = $request->transcationReference;
            $merchantBalance = $request->merchantBalance;

            //TODO: Check type
            if ($type != 'TRANSACTION') {
                $status = [
                    'status'  => 422,
                    'message' => 'type harus berisi TRANSACTION',
                ];

                //TODO: LOG ERROR
                LOG::channel('qris')->error('invoiceID:' . $invoiceNumber . ' | ', $status);

                return response()->json($status, 422);
            }

            //* Tahap 1
            $data = TransaksiOPD::where('invoice_id', $invoiceNumber)->first();
            if ($data == null) {
                $status = [
                    'status'  => 422,
                    'message' => 'Nomor invoice tidak ditemukan.',
                ];

                //TODO: LOG ERROR
                LOG::channel('qris')->error('invoiceID:' . $invoiceNumber . ' | ', $status);

                return response()->json($status, 404);
            }

            //TODO: Cek Status Bayar
            if ($data->status_bayar == 1) {
                $statuCode = $data->ntb == $rrn ? 200 : 404;
                $status = [
                    'status'  => $statuCode,
                    'message' => 'Data ini sudah dibayar menggunakan ' . $data->chanel_bayar,
                ];

                //TODO: LOG ERROR
                LOG::channel('qris')->error('invoiceID:' . $invoiceNumber . ' | ', $status);

                return response()->json($status, $statuCode);
            }

            //TODO: Update Data
            $data->update([
                'ntb' => $rrn,
                'tgl_bayar'  => Carbon::createFromFormat('d/m/Y H:i:s', $transactionDate)->format('Y-m-d H:i:s'),
                'updated_by' => 'BJB From API Callback',
                'status_bayar' => 1,
                'chanel_bayar' => 'QRIS | ' . $customerName,
                'total_bayar_bjb' => $transactionAmount
            ]);

            $status = [
                'code' => 200,
                'description' => 'OK',
                'datetime' => $time->format('Y-m-d') . 'T' . $time->format('H:i:s')
            ];

            //* Tahap 2
            $amount = $data->total_bayar;
            $customerName = $data->nm_wajib_pajak;
            $va_number    = (int) $data->nomor_va_bjb;

            $expiredDateAddMinute = Carbon::createFromFormat('d/m/Y H:i:s', $transactionDate)->format('Y-m-d H:i:s');
            $expiredDateAddMinute = Carbon::parse($expiredDateAddMinute);
            $expiredDateAddMinute->addMinutes(5);
            $expiredDate = $expiredDateAddMinute->format('Y-m-d H:i:s');

            //TODO: Get Token BJB
            $resGetTokenBJB = $this->vabjb->getTokenBJB();
            if ($resGetTokenBJB->successful()) {
                $resJson = $resGetTokenBJB->json();
                if ($resJson['rc'] != 0000)
                    return response()->json([
                        'message' => 'Terjadi kegagalan saat mengambil token. Error Code : ' . $resJson['rc'] . '. Message : ' . $resJson['message'] . ''
                    ], 422);
                $tokenBJB = $resJson['data'];
            } else {
                return response()->json([
                    'message' => "Terjadi kegagalan saat mengambil token. Error Code " . $resGetTokenBJB->getStatusCode() . ". Silahkan laporkan masalah ini pada administrator"
                ], 422);
            }

            //TODO: Update VA BJB
            $resUpdateVABJB = $this->vabjb->updateVaBJB($tokenBJB, $amount, $expiredDate, $customerName, $va_number);
            if ($resUpdateVABJB->successful()) {
                $resJson = $resUpdateVABJB->json();
                if (isset($resJson['rc']) != 0000)
                    return response()->json([
                        'message' => 'Terjadi kegagalan saat memperbarui Virtual Account. Error Code : ' . $resJson['rc'] . '. Message : ' . $resJson['message'] . ''
                    ], 422);
            } else {
                return response()->json([
                    'message' => "Terjadi kegagalan saat memperbarui Virtual Account. Error Code " . $resUpdateVABJB->getStatusCode() . ". Silahkan laporkan masalah ini pada administrator"
                ], 422);
            }

            //TODO: LOG INFO
            LOG::channel('qris')->info('invoiceID:' . $invoiceNumber . ' | ', $status);

            //* Sent callback to Tangselpay
            $urlTangselPay = 'http://192.168.200.160/v1/intern/callback-qris';
            $reqBodyTangselPay = [
                'type' => $type,
                'merchantName' => $merchantName,
                'transactionDate' => $transactionDate,
                'transactionStatus' => $transcationStatus,
                'transactionAmount' => $transactionAmount,
                'transactionReference' => $transcationReference,
                'merchantBalance' => $merchantBalance,
                'customerName' => $customerName,
                'invoiceNumber' => $invoiceNumber,
                'rrn' => $rrn
            ];
            dispatch(new TangselPayCallbackJob($reqBodyTangselPay, $urlTangselPay));

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
