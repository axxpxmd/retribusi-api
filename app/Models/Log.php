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

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Log extends Model
{
    protected $table = 'logs';
    protected $guarded = [];

    public static function storeLog($no_bayar, $id_retribusi, $ntb, $msg_log)
    {
        $data = Log::where('no_bayar', $no_bayar)->first();
        if (!$data) {
            $dataInput = [
                'id_retribusi' => $id_retribusi,
                'no_bayar' => $no_bayar,
                'ntb' => $ntb,
                'msg_log' => $msg_log
            ];
        }
    }
}
