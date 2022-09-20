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

        try {
            $res = Http::post($url, $reqBody);

            $res->json();
            if ($res->successful()) {
                echo 'berhasil';
            } else {
                echo 'server error' . $res;
            }
        } catch (\Throwable $th) {
            echo $th;
        }
    }
}
