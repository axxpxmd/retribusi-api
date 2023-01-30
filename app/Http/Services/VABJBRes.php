<?php

namespace App\Http\Services;

use App\Http\Services\VABJB;

use Illuminate\Support\Facades\Log;

class VABJBRes
{
    public static function getTokenBJBres($jenis)
    {
        $tokenBJB = '';
        $errMsg = '';

        switch ($jenis) {
            case 1:
                $log = 'Get Token (update invoice)';
                break;
            case 2:
                $log = 'Get Token (callbackQRIS)';
                break;
            default:
                # code...
                break;
        }

        $resGetTokenBJB = VABJB::getTokenBJB();
        $resJson = $resGetTokenBJB->json();

        //* LOG Token
        $dataToken = [
            'data' => $resJson
        ];

        if ($resGetTokenBJB->successful()) {
            if ($resJson['rc'] != 0000) {
                $err = true;
                $errMsg = 'Terjadi kegagalan saat mengambil token VA. Message : ' . $resJson['message'];

                Log::channel('token_va')->error($log, $dataToken);
            } else {
                $err = false;
                $tokenBJB = $resJson['data'];
            }
        } else {
            $err = true;
            $err = 'Terjadi kegagalan saat mengambil token. Error Server';
        }

        return [$err, $errMsg, $tokenBJB];
    }

    public static function createVABJBres($tokenBJB, $clientRefnum, $amount, $expiredDate, $customerName, $productCode, $jenis, $no_bayar)
    {
        $VABJB  = '';
        $errMsg = '';

        switch ($jenis) {
            case 1:
                $log = 'Create VA SKRD (create)';
                break;
            case 2:
                $log = 'Create VA SKRD (update)';
                break;
            case 3:
                $log = 'Create VA STRD (perbarui)';
                break;
            default:
                # code...
                break;
        }

        $resCreateVABJB = VABJB::createVABJB($tokenBJB, $clientRefnum, $amount, $expiredDate, $customerName, $productCode);
        $resJson = $resCreateVABJB->json();

        //* LOG VA
        $dataVA = [
            'no_bayar' => $no_bayar,
            'data' => $resJson
        ];
        Log::channel('create_va')->info($log, $dataVA);

        if ($resCreateVABJB->successful()) {
            if (isset($resJson['response_code']) != '0000') {
                $err = true;
                $errMsg = isset($resJson['repsonse_code_desc']) ? 'Terjadi kegagalan saat membuat Virtual Account. Message : ' . $resJson['repsonse_code_desc'] : 'Terjadi kegagalan saat membuat Virtual Account.';
            } else {
                $err = false;
                $VABJB = $resJson['va_number'];
            }
        } else {
            $err = true;
            $errMsg = 'Terjadi kegagalan saat membuat Virtual Account. Error Server';
        }

        return [$err, $errMsg, $VABJB];
    }

    public static function updateVABJBres($tokenBJB, $amount, $expiredDate, $customerName, $va_number, $jenis, $no_bayar)
    {
        $VABJB  = '';
        $errMsg = '';

        switch ($jenis) {
            case 1:
                $log = 'Update VA (update invoice - make VA expired)';
                break;
            case 2:
                $log = 'Update VA (callbackQRIS - make VA expired)';
                break;
            default:
                # code...
                break;
        }

        $resUpdateVABJB = VABJB::updateVaBJB($tokenBJB, $amount, $expiredDate, $customerName, $va_number);
        $resJson = $resUpdateVABJB->json();

        //* LOG VA
        $dataVA = [
            'no_bayar' => $no_bayar,
            'data' => $resJson
        ];
        Log::channel('update_va')->info($log, $dataVA);
        if ($resUpdateVABJB->successful()) {
            if (isset($resJson['response_code']) != '0000') {
                $err = true;
                $errMsg = isset($resJson['repsonse_code_desc']) ? 'Terjadi kegagalan saat memperbarui Virtual Account. Message : ' . $resJson['repsonse_code_desc'] : 'Terjadi kegagalan saat memperbarui Virtual Account.';
            } else {
                $err = false;
                $VABJB = $resJson['va_number'];
            }
        } else {
            $err = true;
            $errMsg = 'Terjadi kegagalan saat memperbarui Virtual Account. Error Server';
        }

        return [$err, $errMsg, $VABJB];
    }

    public static function CheckVABJBres($tokenBJB, $va_number, $jenis, $no_bayar)
    {
        $VABJB  = '';
        $errMsg = '';

        switch ($jenis) {
            case 1:
                $log = 'Check inquiry VA STS (show)';
                break;
            case 2:
                $log = 'Check inquiry VA STS (edit)';
                break;
            default:
                # code...
                break;
        }

        $resCheckVABJB = VABJB::CheckVABJB($tokenBJB, $va_number);
        $resJson = $resCheckVABJB->json();

        //* LOG VA
        $dataVA = [
            'no_bayar' => $no_bayar,
            'data' => $resJson
        ];
        Log::channel('check_va')->info($log, $dataVA);

        if ($resCheckVABJB->successful()) {
            if (isset($resJson['response_code']) != '0000') {
                $err = true;
                $errMsg = isset($resJson['repsonse_code_desc']) ? 'Terjadi kegagalan saat check inquiry Virtual Account. Message : ' . $resJson['repsonse_code_desc'] : 'Terjadi kegagalan saat check inquiry Virtual Account.';
            } else {
                $err = false;
                $VABJB  = $resJson['va_number'];
                $status = $resJson['status'];
                $transactionTime = $resJson['transactions']['transaction_date'];
                $transactionAmount = $resJson['transactions']['transaction_amount'];
            }
        } else {
            $err = true;
            $errMsg = 'Terjadi kegagalan saat check inquiry Virtual Account. Error Server';
        }

        return [$err, $errMsg, $VABJB, $status, $transactionTime, $transactionAmount];
    }
}
