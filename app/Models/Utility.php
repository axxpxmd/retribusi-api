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

use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Model;

class Utility extends Model
{
    public static function getDiffDate($tgl_jatuh_tempo, $tgl_bayar = null)
    {
        $startDate = Carbon::parse($tgl_jatuh_tempo);
        $endDate   = $tgl_bayar ? Carbon::parse($tgl_bayar) : Carbon::now();

        $dayDiff = $endDate->diff($startDate)->format('%r%a');
        $monthDiff = $startDate->diffInMonths($endDate);

        return [$dayDiff, $monthDiff];
    }

    public static function createBunga($tgl_skrd_akhir, $total_bayar, $tgl_bayar = null)
    {
        //TODO: Create Bunga (kenaikan 1% tiap bulan)
        list($dayDiff, $monthDiff) = self::getDiffDate($tgl_skrd_akhir, $tgl_bayar);

        //TODO: Check status bayar
        if ($tgl_bayar) {
            if ($dayDiff >= 0) {
                $kenaikan = 0;
            } else {
                $kenaikan = ((int) $monthDiff + 1) * 1;
            }
        } else {
            $kenaikan = ((int) $monthDiff + 1) * 1;
        }

        $bunga = $kenaikan / 100;
        $jumlahBunga = $total_bayar * $bunga;

        return [$jumlahBunga, $kenaikan];
    }

    public static function isJatuhTempo($tgl_jatuh_tempo, $dateNow)
    {
        if ($dateNow <= $tgl_jatuh_tempo) {
            $jatuh_tempo = false;
        } else {
            $jatuh_tempo = true;
        }

        return $jatuh_tempo;
    }

    public static function checkStatusTTD($status_ttd)
    {
        if ($status_ttd == 1 || $status_ttd == 3) {
            $status_ttd = true;
        } else {
            $status_ttd = false;
        }

        return $status_ttd;
    }
}
