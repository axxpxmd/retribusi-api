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

use App\Http\Services\VABJBRes;
use App\Http\Controllers\Controller;

// Queque
use App\Jobs\CallbackJob;
use App\Jobs\WhatsAppJob;
use App\Jobs\TangselPayCallbackJob;

// Models
use App\Models\TableLog;
use App\Models\TransaksiOPD;

class CallBackController extends Controller
{
    public function __construct(VABJBRes $vabjbres)
    {
        $this->vabjbres = $vabjbres;
    }

    /**
     ** Callback for payment with VA (virtual account)
     */
    public function callBackVA(Request $request)
    {
        //* Get params
        $status    = $request->status;
        $va_number = $request->va_number;
        $client_refnum      = $request->client_refnum;
        $transaction_time   = $request->transaction_time;
        $transaction_amount = $request->transaction_amount;

        $ip     = $request->ip();
        $ipBJB  = config('app.ipbjb');
        $ipBJB2 = config('app.ipbjb2');
        $ipKMNF = config('app.ipkmnf');

        $paramsLog = [
            'ntb'      => md5($client_refnum),
            'no_bayar' => $client_refnum,
            'jenis'    => 'Virtual Account',
            'id_retribusi' => null,
            'params_req' => json_encode($request->all())
        ];

        //TODO: LOG
        LOG::channel('va')->info('status:' . $status . ' | ' . 'va number:' . $va_number . ' | ' . 'client refnum:' . $client_refnum . ' | ' . 'transaction time:' . $transaction_time . ' | ' . 'transaction amount:' . $transaction_amount);

        //* Check Status (status must 2)
        if ($status != 2) {
            $status = [
                'status'  => 404,
                'message' => 'Error, Status harus dibayar penuh.'
            ];

            //TODO: LOG ERROR
            LOG::channel('va')->error('No Bayar:' . $client_refnum . ' | ', $status);

            //* Save log to table (Error)
            TableLog::storeLog(array_merge($paramsLog, ['status' => 2, 'msg_log' => $status]));

            return response()->json($status, 404);
        }

        try {
            //* Check IP
            if ($ip == $ipBJB || $ip != $ipBJB2 || $ip != $ipKMNF) {
                $where = [
                    'nomor_va_bjb' => $va_number,
                    'no_bayar' => $client_refnum
                ];
                $data = TransaksiOPD::where($where)->first();

                //* Check Data
                if ($data == null) {
                    $status = [
                        'status'  => 404,
                        'message' => 'Error, Data tidak ditemukan.'
                    ];

                    //* Save log to table (Error)
                    TableLog::storeLog(array_merge($paramsLog, ['status' => 2, 'msg_log' => $status]));

                    return response()->json($status, 404);
                }

                //* Chek Status Bayar
                if ($data->status_bayar == 1) {
                    $status = [
                        'status'  => 404,
                        'message' => 'Data ini sudah dibayar menggunakan ' . $data->chanel_bayar,
                    ];

                    if ($data->chanel_bayar != 'Virtual Account') {
                        //TODO: LOG ERROR
                        LOG::channel('va')->error('No Bayar:' . $client_refnum . ' | ', $status);

                        //* Save log to table (Error)
                        TableLog::storeLog(array_merge($paramsLog, ['status' => 2, 'msg_log' => $status, 'id_retribusi' => $data->id]));
                    }

                    return response()->json($status, 404);
                }

                $data->update([
                    'ntb' => md5($client_refnum),
                    'tgl_bayar'    => $transaction_time,
                    'updated_by'   => 'BJB From API Callback',
                    'status_bayar' => 1,
                    'chanel_bayar' => 'Virtual Account',
                    'total_bayar_bjb' => $transaction_amount
                ]);

                //* Forward callback from BJB to Client
                if ($data->userApi != null) {
                    $url = $data->userApi->url_callback;
                    $reqBody = [
                        'nomor_va_bjb'  => $va_number,
                        'no_bayar'      => $client_refnum,
                        'waktu_bayar'   => $transaction_time,
                        'jumlah_bayar'  => $transaction_amount,
                        'status_bayar'  => 1,
                        'channel_bayar' => 'Virtual Account'
                    ];
                    dispatch(new CallbackJob($reqBody, $url));
                }

                //* Send invoice from Whatsapp
                if ($data->no_telp) {
                    $params = [
                        'ntb'  => md5($client_refnum),
                        'data' => $data,
                        'tgl_bayar'       => $transaction_time,
                        'chanel_bayar'    => 'Virtual Account',
                        'total_bayar_bjb' => $transaction_amount,
                    ];
                    dispatch(new WhatsAppJob($params));
                }

                //* Sent callback to Tangselpay
                $urlTangselPay = 'http://192.168.200.160/v1/intern/callback-va';
                $reqBodyTangselPay = [
                    'status'        => $status,
                    'va_number'     => $va_number,
                    'client_refnum' => $client_refnum,
                    'transaction_time'   => $transaction_time,
                    'transaction_amount' => $transaction_amount
                ];
                // dispatch(new TangselPayCallbackJob($reqBodyTangselPay, $urlTangselPay)); // belum dipake


                $status = [
                    'response_code'    => 0000,
                    'response_message' => 'Success',
                ];

                //* Save log to table (Success)
                TableLog::storeLog(array_merge($paramsLog, ['status' => 1, 'msg_log' => $status, 'id_retribusi' => $data->id]));

                return response()->json([
                    'response_code'    => 0000,
                    'response_message' => 'Success',
                ]);
            } else {
                return response()->json($status);
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
     ** Callback for payment with QRIS
     */
    public function callbackQRIS(Request $request)
    {
        //TODO: LOG INFO
        LOG::channel('qris')->info('invoiceID:' . $request->invoiceNumber . ' | ' . 'type:' . $request->type . ' | ' . 'transaction date:' . $request->transactionDate . ' | ' . 'transaction amount:' . $request->transactionAmount . ' | ' . 'customer name:' . $request->customerName . ' | ' . 'rrn:' . $request->rrn);

        $this->validate($request, [
            'invoiceNumber'     => 'required',
            'transactionDate'   => 'required',
            'transactionAmount' => 'required'
        ]);

        /* Tahapan :
         *  1. tmtransaksi_opd (update)
         *  2. Make VA Expired
         *  3. Send invoice from Whatsapp
         */

        try {
            $time = Carbon::now();

            //* Get Params
            $type = $request->type;
            $rrn  = $request->rrn;
            $customerName    = $request->customerName;
            $merchantName    = $request->merchantName;
            $invoiceNumber   = $request->invoiceNumber;
            $merchantBalance = $request->merchantBalance;
            $transactionDate      = $request->transactionDate;
            $transactionAmount    = (int) str_replace(['.', 'Rp', ' ', ','], '', $request->transactionAmount);
            $transcationStatus    = $request->transcationStatus;
            $transactionReference = $request->transactionReference;

            $paramsLog = [
                'ntb'      => $rrn,
                'no_bayar' => null,
                'jenis'    => 'QRIS',
                'id_retribusi' => null,
                'params_req'   => json_encode($request->all())
            ];

            //* Check type
            if ($type != 'TRANSACTION') {
                $status = [
                    'status'  => 422,
                    'message' => 'type harus berisi TRANSACTION',
                ];

                //* Save log to table (Error)
                TableLog::storeLog(array_merge($paramsLog, ['status' => 2, 'msg_log' => $status]));

                //TODO: LOG ERROR
                LOG::channel('qris')->error('invoiceID:' . $invoiceNumber . ' | ', $status);

                return response()->json($status, 422);
            }

            $data = TransaksiOPD::where('invoice_id', $invoiceNumber)->first();
            if (!$data) {
                $status = [
                    'status'  => 422,
                    'message' => 'Nomor invoice tidak ditemukan.',
                ];

                //* Save log to table (Error)
                TableLog::storeLog(array_merge($paramsLog, ['status' => 2, 'msg_log' => $status]));

                //TODO: LOG ERROR
                LOG::channel('qris')->error('invoiceID:' . $invoiceNumber . ' | ', $status);

                return response()->json($status, 404);
            }

            //* Cek Status Bayar
            if ($data->status_bayar == 1) {
                $statuCode = $data->ntb == $rrn ? 200 : 404;

                $status = [
                    'status'  => $statuCode,
                    'message' => 'Data ini sudah dibayar menggunakan ' . $data->chanel_bayar,
                ];

                //TODO: LOG ERROR
                LOG::channel('qris')->error('invoiceID:' . $invoiceNumber . ' | ', $status);

                //* Save log to table (Error)
                if ($statuCode == 404) {
                    TableLog::storeLog(array_merge($paramsLog, ['status' => 2, 'msg_log' => $status, 'id_retribusi' => $data->id, 'no_bayar' => $data->no_bayar]));
                }

                return response()->json($status, $statuCode);
            }

            //* Tahap 1
            $data->update([
                'ntb'        => $rrn,
                'tgl_bayar'  => Carbon::createFromFormat('d/m/Y H:i:s', $transactionDate)->format('Y-m-d H:i:s'),
                'updated_by' => 'BJB From API Callback',
                'status_bayar'    => 1,
                'chanel_bayar'    => 'QRIS | ' . $customerName,
                'total_bayar_bjb' => $transactionAmount
            ]);

            //* Tahap 2
            $amount       = \strval((int) str_replace(['.', 'Rp', ' '], '', $data->jumlah_bayar));
            $customerName = $data->nm_wajib_pajak;
            $va_number    = (int) $data->nomor_va_bjb;
            $clientRefnum = $data->no_bayar;
            $expiredDate  = Carbon::now()->addMinutes(20)->format('Y-m-d H:i:s');

            if ($va_number) {
                //TODO: Get Token VA
                list($err, $errMsg, $tokenBJB) = $this->vabjbres->getTokenBJBres(2);

                //TODO: Update VA BJB (make Va expired)
                list($err, $errMsg, $VABJB) = $this->vabjbres->updateVABJBres($tokenBJB, $amount, $expiredDate, $customerName, $va_number, 2, $clientRefnum);
            }

            //* Forward callback from BJB to Client
            if ($data->userApi != null) {
                $url = $data->userApi->url_callback;
                $reqBody = [
                    'nomor_va_bjb'  => $va_number,
                    'no_bayar'      => $data->no_bayar,
                    'waktu_bayar'   => Carbon::createFromFormat('d/m/Y H:i:s', $transactionDate)->format('Y-m-d H:i:s'),
                    'jumlah_bayar'  => $transactionAmount,
                    'status_bayar'  => 1,
                    'channel_bayar' => 'QRIS | ' . $customerName
                ];
                dispatch(new CallbackJob($reqBody, $url));
            }

            //* Sent callback to Tangselpay
            $urlTangselPay = 'http://192.168.200.160/v1/intern/callback-qris';
            $reqBodyTangselPay = [
                'type' => $type,
                'rrn'  => $rrn,
                'customerName'    => $customerName,
                'invoiceNumber'   => $invoiceNumber,
                'merchantName'    => $merchantName,
                'transactionDate' => $transactionDate,
                'merchantBalance' => $merchantBalance,
                'transactionStatus'    => $transcationStatus,
                'transactionAmount'    => $transactionAmount,
                'transactionReference' => $transactionReference,
            ];
            // dispatch(new TangselPayCallbackJob($reqBodyTangselPay, $urlTangselPay));


            //* Tahap 3
            if ($data->no_telp) {
                $params = [
                    'ntb'  => $rrn,
                    'data' => $data,
                    'tgl_bayar'       => Carbon::createFromFormat('d/m/Y H:i:s', $transactionDate)->format('Y-m-d H:i:s'),
                    'chanel_bayar'    => 'QRIS | ' . $customerName,
                    'total_bayar_bjb' => $transactionAmount,
                ];
                dispatch(new WhatsAppJob($params));
            }

            $status = [
                'code' => 200,
                'description' => 'OK',
                'datetime' => $time->format('Y-m-d') . 'T' . $time->format('H:i:s')
            ];

            //TODO: LOG INFO
            LOG::channel('qris')->info('invoiceID:' . $invoiceNumber . ' | ', $status);

            //* Save log to table (Sucess)
            TableLog::storeLog(array_merge($paramsLog, ['status' => 1, 'msg_log' => $status, 'id_retribusi' => $data->id, 'no_bayar' => $data->no_bayar]));

            return response()->json([
                'metadata'  => null,
                'status'    => $status,
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
