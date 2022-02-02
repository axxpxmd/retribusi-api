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

namespace App\Http\Controllers\Client;

use Illuminate\Http\Request;

use App\Http\Services\VABJB;
use App\Http\Controllers\Controller;

// Models
use App\Models\UserDetail;
use App\Models\TransaksiOPD;

class CallbackVAController extends Controller
{
    public function __construct(VABJB $vabjb)
    {
        $this->vabjb = $vabjb;
    }

    public function callbackVABJB(Request $request, $id)
    {
        $api_key = $request->header('API-Key');
        $user    = UserDetail::where('api_key', $api_key)->first();
        if (!$api_key || !$user) {
            return response()->json([
                'status'  => 401,
                'message' => 'Invalid API Key!'
            ], 401);
        }

        try {
            $data = TransaksiOPD::find($id);

            $va_number = (int) $data->nomor_va_bjb;
            $status_bayar = $data->status_bayar;

            if ($status_bayar == 0 && $va_number != null) {
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

                //TODO: Check VA BJB
                $resCheckVABJB = $this->vabjb->CheckVABJB($tokenBJB, $va_number);
                if ($resCheckVABJB->successful()) {
                    $resJson = $resCheckVABJB->json();
                    if (isset($resJson['rc']) != 0000)
                        return redirect()
                            ->route($this->route . 'index')
                            ->withErrors('Terjadi kegagalan saat mengecek status pembayaran VA BJB. Error Code : ' . $resJson['rc'] . '. Message : ' . $resJson['message'] . '');
                    $VABJB  = $resJson['va_number'];
                    $status = $resJson['status'];
                    $transactionTime = $resJson['transactions']['transaction_date'];
                    $transactionAmount = $resJson['transactions']['transaction_amount'];
                } else {
                    return redirect()
                        ->route($this->route . 'index')
                        ->withErrors("Terjadi kegagalan saat mengecek status pembayaran VA BJB. Error Code " . $resCheckVABJB->getStatusCode() . ". Silahkan laporkan masalah ini pada administrator");
                }

                //TODO: Update tmtransaksi_opd
                if ($status == 2) {
                    $ntb = \md5($data->no_bayar);

                    $data->update([
                        'ntb'        => $ntb,
                        'tgl_bayar'  => $transactionTime,
                        'updated_by' => 'Bank BJB | Check Inquiry',
                        'status_bayar' => 1,
                        'chanel_bayar' => 'Virtual Account',
                        'total_bayar_bjb' => $transactionAmount,
                    ]);
                }
            }

            return response()->json([
                'status'  => 200,
                'message' => 'Succesfully'
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
            ], 500);
        }
    }
}
