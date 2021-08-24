<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Http;

class WhatsappClient
{
    protected $host;
    protected $number;
    protected $key;
    protected $url;
    protected $waSend;

    public function __construct()
    {
        $this->waSend = config('app.wa_send');
        $this->host = config('app.wa_host');
        $this->number = config('app.wa_number');
        $this->key = config('app.wa_key');
        $this->url = '/wa_master/public/send-text';
    }

    public function sendPersonal($message)
    {
        // send whatsapp when waSend = true
        if ($this->waSend) {
            try {
                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                ])->post($this->host . $this->url, [
                    'number'  => $this->number,
                    'api_key' => $this->key,
                    'message' => $message,
                ]);
                return $response;
            } catch (Throwable $th) {
                throw $th;
            }
        } else {
            return response('Wa send false', 200);
        }
    }
}
