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

namespace App\Http\Controllers\Client;

use Illuminate\Http\Request;

use App\Http\Controllers\Controller;

class BookingController extends Controller
{
    public function createBooking(Request $request)
    {
        $this->validate($request, [
            'id_opd' => 'required',
            'nama' => 'required',
            'kecamatan_id' => 'required',
            'kelurahan_id' => 'required',
            'alamat' => 'required',
            'email' => 'required',
            'no_telp' => 'required',
            'tgl_booking' => 'required'
        ]);

        
        
    }
}
