<?php

namespace App\Helpers;

// Models
use App\Models\OPDJenisPendapatan;

class Helper
{
    public static function checkExistedJenisPendapatan($opd_id, $jenis_pendapatan_id)
    {
        $jenis_pendapatans = OPDJenisPendapatan::getJenisPendapatanByOpd($opd_id);

        if (in_array($jenis_pendapatan_id, $jenis_pendapatans->pluck('id')->toArray())) {
            return true;
        } else {
            return false;
        }
    }
}
