<?php

namespace App\Jobs;

use Illuminate\Support\Facades\Http;

class CallbackJob extends Job
{
    protected $reqBody;
    protected $url;
    public $tries = 2;

    public function __construct($reqBody, $url)
    {
        $this->reqBody = $reqBody;
        $this->url = $url;
    }

    public function handle()
    {
        $url = $this->url;
        $reqBody = $this->reqBody;

        $res = Http::asForm()->post($url, $reqBody);

        if ($res->successful()) {
            echo 'Berhasil';
        } else {
            echo 'Gagal';
        }
    }
}
