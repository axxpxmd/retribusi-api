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
}
