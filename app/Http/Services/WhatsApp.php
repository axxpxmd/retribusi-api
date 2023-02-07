<?php

namespace App\Http\Services;

use Carbon\Carbon;

use Illuminate\Support\Facades\Http;

class WhatsApp
{
    public static function transactionSuccess($tgl_bayar, $ntb, $chanel_bayar, $total_bayar_bjb, $data)
    {
        $endpoint = config('app.wagateway_ipserver');
        $api_key  = config('app.wagateway_apikey');
        $url_retribusi = config('app.url_retribusi');
        $link = $url_retribusi . base64_encode($data->id) . "?send_sts=1";

        //* Send message to WA
        $text = "*PEMBAYARAN RETRIBUSI BERHASIL* 

Untuk *" . $data->rincian_jenis->rincian_pendapatan . "*

*Tanggal Bayar* : " . Carbon::parse($tgl_bayar)->format('d F Y | H:i:s') . "
*Nomor Transaksi* : " . $ntb . "
*Metode Pembayaran* : " . $chanel_bayar . "
*Nominal* : Rp. " . number_format($total_bayar_bjb) . "
------------------------------------------------------
*Nama Pelanggan* : " . $data->nm_wajib_pajak . "
*No Pendaftaran* : " . $data->nmr_daftar . "
*No SKRD* : " . $data->no_skrd . "

*Untuk data selengkapnya bisa dilihat pada link dibawah ini*.
" . $link . "

Retribusi, Tangerang Selatan.
";
        Http::post($endpoint . 'send-text', [
            'number'  => $data->no_telp,
            'api_key' => $api_key,
            'message' => $text
        ]);
    }
}
