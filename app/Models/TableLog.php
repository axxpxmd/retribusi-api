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

class TableLog extends Model
{
    protected $table = 'logs';
    protected $guarded = [];

    public static function storeLog($params)
    {
        $ntb = $params['ntb'];
        $msg_log  = $params['msg_log'];
        $no_bayar = $params['no_bayar'];
        $status   = $params['status'];
        $jenis    = $params['jenis'];
        $id_retribusi = $params['id_retribusi'];

        $data = TableLog::where('no_bayar', $no_bayar)->first();
        $dataInput = [
            'ntb' => $ntb,
            'msg_log'  => json_encode($msg_log),
            'no_bayar' => $no_bayar,
            'status'   => $status,
            'jenis'    => $jenis,
            'id_retribusi' => $id_retribusi,
        ];
        if (!$data) {
            TableLog::create($dataInput);
        } else { 
            $data->update($dataInput);
        }
    }
}