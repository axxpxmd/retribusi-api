<?php

namespace App\Jobs;

use App\Http\Services\WhatsApp;

class WhatsAppJob extends Job
{
    protected $params;
    public $tries = 1;

    public function __construct($params)
    {
        $this->params = $params;
    }

    public function handle()
    {
        $ntb  = $this->params['ntb'];
        $data = $this->params['data'];
        $tgl_bayar       = $this->params['tgl_bayar'];
        $chanel_bayar    = $this->params['chanel_bayar'];
        $total_bayar_bjb = $this->params['total_bayar_bjb'];
 
        WhatsApp::transactionSuccess($tgl_bayar, $ntb, $chanel_bayar, $total_bayar_bjb, $data);
    }
}
