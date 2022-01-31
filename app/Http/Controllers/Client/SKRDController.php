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

use App\Http\Services\VABJB;
use App\Http\Controllers\Controller;

// Models
use App\Models\UserDetail;
use App\Models\TransaksiOPD;

class SKRDController extends Controller
{
    public function __construct(VABJB $vabjb)
    {
        $this->vabjb = $vabjb;
    }

    public function index(Request $request)
    {
        $api_key = $request->header('API-Key');
        if (!$api_key) {
            return response()->json([
                'status'  => 401,
                'message' => 'Invalid API Key!'
            ], 401);
        }

        try {
            $user = UserDetail::where('api_key', $api_key)->first();
            if (!$user) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'User tidak ditemukan!'
                ], 403);
            }

            //* Params
            $end   = $request->end;
            $start = $request->start;
            $length = $request->length;
            $opd_id = $user->opd_id;
            $no_skrd    = $request->no_skrd;
            $status_ttd = $request->status_ttd;

            $datas = TransaksiOPD::querySKRD($length, $opd_id, $no_skrd, $status_ttd, $start, $end);

            return response()->json([
                'status'  => 200,
                'message' => 'Succesfully',
                'datas'   => $datas
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    public function store()
    {
        dd('input jalan');
    }
}
