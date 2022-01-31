<?php

namespace App\Http\Services;

use Carbon\Carbon;
use Firebase\JWT\JWT;

use Illuminate\Support\Facades\Http;

class VABJB
{
    public static function getTokenBJB()
    {
        /* Get Token From Bank BJB
         * TOKEN REQUEST (POST /oauth/client/token)
         */
        $timestamp_now   = Carbon::now()->timestamp;
        $timestamp_1hour = Carbon::now()->addHour()->timestamp;

        $url = config('app.ip_api_bjb');
        $client_id = config('app.client_id_bjb');
        $key = config('app.key_bjb');

        $payload   = array(
            "sub" => "va-online",
            "aud" => "access-token",
            "iat" => $timestamp_now,
            "exp" => $timestamp_1hour
        );

        $jwt = JWT::encode($payload, $key, 'HS256', $client_id); // Create JWT Signature (HMACSHA256)
        $res = Http::contentType("text/plain")->send('POST', $url . 'oauth/client/token', [
            'body' => $jwt
        ]);

        return $res;
    }

    public static function createVABJB($tokenBJB, $clientRefnum, $amount, $expiredDate, $customerName, $productCode)
    {
        /* Create Virtual Account from Bank BJB
         * CREATE BILLING REQUEST (POST /billing)
         */

        $url = config('app.ip_api_bjb');
        $key = config('app.key_bjb');
        $timestamp_now = Carbon::now()->timestamp;

        $cin         = config('app.cin_bjb');
        $clientType  = "1";
        $billingType = "f";
        $vaType      = "a";
        $currency    = "360";
        $description = "Pembayaran Retribusi";

        // Base Signature
        $bodySignature = '{"cin":"' . $cin . '","client_type":"' . $clientType . '","product_code":"' . $productCode . '","billing_type":"' . $billingType . '","va_type":"' . $vaType . '","client_refnum":"' . $clientRefnum . '","amount":"' . $amount . '","currency":"' . $currency . '","expired_date":"' . $expiredDate . '","customer_name":"' . $customerName . '","description":"' . $description . '"}';
        $signature = 'path=/billing&method=POST&token=' . $tokenBJB . '&timestamp=' . $timestamp_now . '&body=' . $bodySignature . '';
        $sha256    = hash_hmac('sha256', $signature, $key);

        // Body / Payload
        $reqBody = [
            "cin"           => $cin,
            "client_type"   => $clientType,
            "product_code"  => $productCode,
            "billing_type"  => $billingType,
            "va_type"       => $vaType,
            "client_refnum" => $clientRefnum,
            "amount"   => $amount,
            "currency" => $currency,
            "expired_date"  => $expiredDate,
            "customer_name" => $customerName,
            "description"   => $description,
        ];

        $res = Http::withHeaders([
            'Authorization' => 'Bearer ' . $tokenBJB,
            'BJB-Timestamp' => $timestamp_now,
            'BJB-Signature' => $sha256,
            'Content-Type'  => 'application/json'
        ])->post($url . 'billing', $reqBody);

        return $res;
    }

    public static function updateVaBJB($tokenBJB, $amount, $expiredDate, $customerName, $va_number)
    {
        /* Update Virtual Account from Bank BJB
         * UPDATE BILLING REQUEST (POST /billing/<cin>/<va_number>)
         */

        $url = config('app.ip_api_bjb');
        $key = config('app.key_bjb');
        $timestamp_now = Carbon::now()->timestamp;

        $cin      = config('app.cin_bjb');
        $currency = "360";
        $description = "Pembayaran Retribusi";

        // Base Signature
        $bodySignature = '{"amount":"' . $amount . '","currency":"' . $currency . '","expired_date":"' . $expiredDate . '","customer_name":"' . $customerName . '","description":"' . $description . '"}';
        $signature = 'path=/billing/' . $cin . '/' . $va_number . '&method=POST&token=' . $tokenBJB . '&timestamp=' . $timestamp_now . '&body=' . $bodySignature . '';
        $sha256    = hash_hmac('sha256', $signature, $key);

        // Body / Payload
        $reqBody = [
            "amount"   => $amount,
            "currency" => $currency,
            "expired_date"  => $expiredDate,
            "customer_name" => $customerName,
            "description"   => $description
        ];

        $path = 'billing/' . $cin . '/' . $va_number . '';
        $res  = Http::withHeaders([
            'Authorization' => 'Bearer ' . $tokenBJB,
            'BJB-Timestamp' => $timestamp_now,
            'BJB-Signature' => $sha256,
            'Content-Type'  => 'application/json'
        ])->post($url . $path, $reqBody);

        return $res;
    }


    public static function CheckVABJB($tokenBJB, $va_number)
    {
        /* Check Virtual Account from Bank BJB
         * INQUIRY BILLING REQUEST (GET /billing/<cin>/<va_number>)
         */

        $url = config('app.ip_api_bjb');
        $key = config('app.key_bjb');
        $cin = config('app.cin_bjb');
        $timestamp_now = Carbon::now()->timestamp;

        // Base Signature
        $signature = 'path=/billing/' . $cin . '/' . $va_number . '&method=GET&token=' . $tokenBJB . '&timestamp=' . $timestamp_now . '&body=';
        $sha256    = hash_hmac('sha256', $signature, $key);

        $path = 'billing/' . $cin . '/' . $va_number . '';
        $res  = Http::withHeaders([
            'Authorization' => 'Bearer ' . $tokenBJB,
            'BJB-Timestamp' => $timestamp_now,
            'BJB-Signature' => $sha256,
            'Content-Type'  => 'application/json'
        ])->get($url . $path);

        return $res;
    }
}
