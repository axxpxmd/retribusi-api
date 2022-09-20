<?php

namespace App\Jobs;

use Illuminate\Support\Facades\Http;

class TangselPayCallbackJob extends Job
{
    protected $reqBody;
    protected $url;
    public $tries = 2;

    public function __construct($reqBodyTangselPay, $urlTangselPay)
    {
        $this->reqBody = $reqBodyTangselPay;
        $this->url = $urlTangselPay;
    }

    public function handle()
    {
        $url = $this->url;
        $reqBody = $this->reqBody;

        $res = Http::post($url, $reqBody);

        $resJson = $res->json();
        if ($res->successful()) {
            if ($resJson['status'] == 200) {
                echo 'berhasil';
            } else {
                echo $resJson['status'] . ' Error, status code tidak sesuai';
            }
        } else {
            echo 'gagal kirim callback';
        }
    }
}
