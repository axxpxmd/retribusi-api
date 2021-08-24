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

class OPD extends Model
{
    protected $table = 'tmopds';
    protected $guarded = [];

    public function transaksi_opd()
    {
        return $this->hasMany(TransaksiOPD::class, 'id_opd', 'id');
    }
}
